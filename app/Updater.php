<?php
declare(strict_types=1);

namespace App;

defined('APP_ROOT') || exit;

use RuntimeException;
use ZipArchive;

/**
 * Werkt de code bij naar de nieuwste versie op GitHub.
 *
 * Is de installatie een git-clone, dan via `git pull --ff-only`; anders wordt de
 * zip van GitHub gedownload en over de projectmap uitgepakt. Bestanden die niet in
 * de repository staan (uploads, ../.env, ../data) blijven altijd ongemoeid. Bij de
 * zip-methode geldt dat ook voor bestaande iconen in public/icons/: die worden nooit
 * overschreven of verwijderd; alleen nieuwe iconen uit de repository komen erbij.
 */
final readonly class Updater
{
    private const string DOCUMENT = 'update';
    /** Buildnummers per commit; een commit houdt altijd hetzelfde nummer, dus ophalen hoeft maar één keer. */
    private const string BUILDS_DOCUMENT = 'builds';
    private const int KEEP_BACKUPS = 3;
    /** Moeten in de download zitten; anders is het geen geldige versie van deze app. */
    private const array REQUIRED_FILES = ['public/index.php', 'app/bootstrap.php', 'app/App.php'];

    public function __construct(private Config $config, private JsonStore $store) {}

    public function status(): array
    {
        $latest = $this->latestCommit();
        $installed = $this->installedSha();
        return [
            'mode'      => $this->isGitCheckout() ? 'git' : 'zip',
            'repo'      => $this->config->updateRepo,
            'branch'    => $this->config->updateBranch,
            'installed' => $installed,
            'build'     => $installed === null ? null : $this->buildNumber($installed),
            'latest'    => $latest + ['build' => $this->buildNumber($latest['sha'])],
            'current'   => $installed !== null && $installed === $latest['sha'],
            'writable'  => is_writable(APP_ROOT) && is_writable(APP_ROOT . '/app') && is_writable(PUBLIC_ROOT),
        ];
    }

    /** @return array{mode: string, sha: ?string, log: string} */
    public function run(): array
    {
        set_time_limit(180);
        $result = $this->isGitCheckout() ? $this->gitPull() : $this->installZip();
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        return $result;
    }

    /* ---------- git ---------- */

    private function isGitCheckout(): bool
    {
        return is_dir(APP_ROOT . '/.git');
    }

    private function gitPull(): array
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('Deze installatie gebruikt git, maar PHP mag geen commando\'s uitvoeren (proc_open uitgeschakeld). Gebruik "git pull" via SSH.');
        }
        // PHP-FPM zet vaak geen HOME; git en ssh hebben die nodig voor hun configuratie.
        $home = function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['dir'] ?? null) : null;
        $env = ['HOME' => getenv('HOME') ?: $home ?: dirname(APP_ROOT), 'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin', 'GIT_TERMINAL_PROMPT' => '0'];

        $process = proc_open(['git', '-C', APP_ROOT, 'pull', '--ff-only'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, APP_ROOT, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('git kon niet worden gestart.');
        }
        $log = trim(stream_get_contents($pipes[1]) . "\n" . stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException("git pull mislukt:\n" . $log);
        }
        return ['mode' => 'git', 'sha' => $this->installedSha(), 'log' => $log];
    }

    /** Leest de huidige commit rechtstreeks uit .git, zonder git aan te roepen. */
    private function gitHeadSha(): ?string
    {
        $git = APP_ROOT . '/.git';
        $head = trim((string) @file_get_contents("{$git}/HEAD"));
        if (!str_starts_with($head, 'ref: ')) {
            return preg_match('/^[0-9a-f]{40}$/', $head) ? $head : null;
        }
        $ref = substr($head, 5);
        $sha = trim((string) @file_get_contents("{$git}/{$ref}"));
        if (preg_match('/^[0-9a-f]{40}$/', $sha)) {
            return $sha;
        }
        foreach (@file("{$git}/packed-refs", FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^([0-9a-f]{40}) (.+)$/', $line, $m) && $m[2] === $ref) {
                return $m[1];
            }
        }
        return null;
    }

    /* ---------- zip ---------- */

    private function installedSha(): ?string
    {
        if ($this->isGitCheckout()) {
            return $this->gitHeadSha();
        }
        $sha = $this->store->read(self::DOCUMENT, [])['sha'] ?? null;
        return is_string($sha) ? $sha : null;
    }

    private function installZip(): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('De PHP-extensie "zip" ontbreekt op deze server.');
        }
        $latest = $this->latestCommit();
        $sha = $latest['sha'];

        $tmpDir = $this->config->dataDir . '/tmp';
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0770, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('Tijdelijke map kan niet worden aangemaakt.');
        }
        $zipFile = "{$tmpDir}/update-{$sha}.zip";
        try {
            file_put_contents($zipFile, $this->http("https://codeload.github.com/{$this->config->updateRepo}/zip/{$sha}"));
            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== true) {
                throw new RuntimeException('Download is geen geldig zip-bestand.');
            }
            $files = self::zipEntries($zip);
            foreach (self::REQUIRED_FILES as $required) {
                if (!isset($files[$required])) {
                    $zip->close();
                    throw new RuntimeException("Download is onvolledig ({$required} ontbreekt); er is niets gewijzigd.");
                }
            }

            $previous = $this->store->read(self::DOCUMENT, [])['files'] ?? [];
            $previous = is_array($previous) ? $previous : [];
            $known = array_flip($previous);

            // Iconen zijn gebruikersdata: alleen nieuwe iconen uit de repository toevoegen,
            // en alleen als er lokaal nog niets met die naam staat; nooit overschrijven of verwijderen.
            $install = array_filter($files, static fn(string $path): bool => !self::isIcon($path)
                || (!isset($known[$path]) && !file_exists(APP_ROOT . '/' . $path)), ARRAY_FILTER_USE_KEY);
            $removed = array_values(array_filter(
                array_diff($previous, array_keys($files)),
                static fn(string $path): bool => !self::isIcon($path),
            ));
            $backup = $this->backup([...array_keys($install), ...$removed]);

            foreach ($install as $path => $index) {
                $this->writeFile($path, (string) $zip->getFromIndex($index));
            }
            $zip->close();
            foreach ($removed as $path) {
                if (self::isSafePath($path) && is_file(APP_ROOT . '/' . $path)) {
                    unlink(APP_ROOT . '/' . $path);
                }
            }
        } finally {
            @unlink($zipFile);
        }

        $this->store->write(self::DOCUMENT, [
            'sha'          => $sha,
            'date'         => $latest['date'],
            'installed_at' => date('c'),
            'files'        => array_keys($files),
        ]);

        $newIcons = count(array_filter(array_keys($install), self::isIcon(...)));
        $log = sprintf(
            "%d bestanden bijgewerkt, %d nieuwe iconen toegevoegd, %d verwijderd.\nBestaande iconen zijn niet aangeraakt.\nBackup van de vorige versie: %s",
            count($install) - $newIcons, $newIcons, count($removed), $backup ? basename($backup) : 'geen',
        );
        return ['mode' => 'zip', 'sha' => $sha, 'log' => $log];
    }

    /**
     * Bestanden in de zip, zonder de hoofdmap die GitHub eromheen zet.
     * @return array<string, int> relatief pad => index in de zip
     */
    private static function zipEntries(ZipArchive $zip): array
    {
        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $path = substr($name, (int) strpos($name, '/') + 1);
            if ($path === '' || str_ends_with($path, '/')) {
                continue;
            }
            if (!self::isSafePath($path)) {
                throw new RuntimeException("Ongeldig pad in download: {$path}");
            }
            $files[$path] = $i;
        }
        return $files;
    }

    private static function isIcon(string $path): bool
    {
        return str_starts_with($path, 'public/icons/');
    }

    private static function isSafePath(string $path): bool
    {
        return $path !== ''
            && !str_starts_with($path, '/')
            && !str_contains($path, "\0")
            && !str_contains($path, '\\')
            && !in_array('..', explode('/', $path), true)
            && !str_starts_with($path, '.git/');
    }

    /** Schrijft atomair (tijdelijk bestand + rename), zodat een bezoeker nooit een half bestand krijgt. */
    private function writeFile(string $path, string $contents): void
    {
        $target = APP_ROOT . '/' . $path;
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Map kan niet worden aangemaakt: {$path}");
        }
        $tmp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $contents) === false || !rename($tmp, $target)) {
            @unlink($tmp);
            throw new RuntimeException("Schrijven mislukt (schrijfrechten?): {$path}");
        }
    }

    /** Zipt de huidige versie van de bestanden die worden overschreven of verwijderd; bewaart de laatste paar. */
    private function backup(array $paths): ?string
    {
        $dir = $this->config->dataDir . '/backups';
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('Backupmap kan niet worden aangemaakt.');
        }
        $file = $dir . '/code-' . date('Ymd-His') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Backup kan niet worden gemaakt; er is niets gewijzigd.');
        }
        $count = 0;
        foreach (array_unique($paths) as $path) {
            if (self::isSafePath($path) && is_file(APP_ROOT . '/' . $path)) {
                $zip->addFile(APP_ROOT . '/' . $path, $path);
                $count++;
            }
        }
        if (!$zip->close() || $count === 0) {
            @unlink($file);
            return null;
        }

        $backups = glob($dir . '/code-*.zip') ?: [];
        rsort($backups);
        foreach (array_slice($backups, self::KEEP_BACKUPS) as $old) {
            @unlink($old);
        }
        return $file;
    }

    /* ---------- GitHub ---------- */

    /** @return array{sha: string, date: string, message: string} */
    private function latestCommit(): array
    {
        $url = "https://api.github.com/repos/{$this->config->updateRepo}/commits/" . rawurlencode($this->config->updateBranch);
        $data = json_decode($this->http($url, ['Accept: application/vnd.github+json']), true);
        if (!is_array($data) || !preg_match('/^[0-9a-f]{40}$/', (string) ($data['sha'] ?? ''))) {
            throw new RuntimeException('Onverwacht antwoord van GitHub.');
        }
        return [
            'sha'     => $data['sha'],
            'date'    => (string) ($data['commit']['committer']['date'] ?? ''),
            'message' => strtok((string) ($data['commit']['message'] ?? ''), "\n") ?: '',
        ];
    }

    /**
     * Oplopend buildnummer: het aantal commits tot en met deze commit.
     * GitHub geeft dat niet direct; met één commit per pagina is het nummer van de laatste pagina het totaal.
     * Null als GitHub de commit niet kent (bijv. een lokale, nog niet gepushte commit).
     */
    private function buildNumber(string $sha): ?int
    {
        $cache = $this->store->read(self::BUILDS_DOCUMENT, []);
        if (is_int($cache[$sha] ?? null)) {
            return $cache[$sha];
        }
        try {
            $body = $this->http("https://api.github.com/repos/{$this->config->updateRepo}/commits?per_page=1&sha={$sha}", ['Accept: application/vnd.github+json'], $responseHeaders);
        } catch (RuntimeException) {
            return null;
        }
        $link = implode(',', preg_grep('/^link:/i', $responseHeaders));
        $build = preg_match('/[?&]page=(\d+)>; rel="last"/', $link, $m) ? (int) $m[1] : count((array) json_decode($body, true));
        if ($build > 0) {
            $this->store->write(self::BUILDS_DOCUMENT, [$sha => $build] + (is_array($cache) ? $cache : []));
        }
        return $build ?: null;
    }

    private function http(string $url, array $headers = [], ?array &$responseHeaders = null): string
    {
        $headers[] = 'User-Agent: kabelkrant-home-updater';
        $responseHeaders = [];
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                    $responseHeaders[] = trim($line);
                    return strlen($line);
                },
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
        } else {
            $context = stream_context_create(['http' => ['header' => implode("\r\n", $headers), 'timeout' => 120, 'ignore_errors' => true]]);
            $body = @file_get_contents($url, false, $context);
            $responseHeaders = $http_response_header ?? [];
            $status = (int) (preg_match('#^HTTP/\S+ (\d{3})#', $http_response_header[0] ?? '', $m) ? $m[1] : 0);
            $error = 'geen verbinding';
        }
        if (!is_string($body) || $status !== 200) {
            throw new RuntimeException($status === 0 ? "GitHub niet bereikbaar ({$error})." : "GitHub gaf status {$status}.");
        }
        return $body;
    }
}
