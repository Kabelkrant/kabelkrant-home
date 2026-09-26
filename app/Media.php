<?php
declare(strict_types=1);

namespace App;

defined('APP_ROOT') || exit;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

/**
 * Beheer van afbeeldingsbestanden: icons (icons/) en huisstijl-uploads (branding/).
 * Uploads worden op inhoud gecontroleerd (niet alleen op extensie) en SVG's
 * worden ontdaan van scripts en event-handlers.
 */
final readonly class Media
{
    private const array EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico'];
    private const array MIME_TYPES = [
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'svg'  => ['image/svg+xml', 'image/svg', 'text/xml', 'application/xml', 'text/plain', 'text/html'],
        'ico'  => ['image/vnd.microsoft.icon', 'image/x-icon', 'application/octet-stream'],
    ];
    public const int MAX_ICON_BYTES = 2 * 1024 * 1024;
    public const int MAX_BRANDING_BYTES = 8 * 1024 * 1024;

    /** Bestandsnamen die bij het opruimen van branding/ nooit worden verwijderd. */
    private const string BRANDING_UPLOAD_PATTERN = '/^(logo|favicon|background)-[0-9a-f]{8}\.[a-z]+$/';

    public function __construct(private Config $config) {}

    /** @return list<array{file: string, path: string, size: int}> */
    public function listIcons(): array
    {
        $dir = $this->config->iconsDir();
        $icons = [];
        foreach (is_dir($dir) ? scandir($dir) : [] as $file) {
            if (is_file("{$dir}/{$file}") && self::hasAllowedExtension($file)) {
                $icons[] = ['file' => $file, 'path' => "icons/{$file}", 'size' => filesize("{$dir}/{$file}")];
            }
        }
        usort($icons, static fn(array $a, array $b): int => strnatcasecmp($a['file'], $b['file']));
        return $icons;
    }

    /** Slaat een geüpload icoon op onder een (zo nodig ontdubbelde) veilige naam. */
    public function uploadIcon(array $upload): string
    {
        $ext  = $this->checkUpload($upload, self::MAX_ICON_BYTES);
        $base = self::slug(pathinfo((string) $upload['name'], PATHINFO_FILENAME)) ?: 'icoon';
        $dir  = $this->config->iconsDir();

        $file = "{$base}.{$ext}";
        for ($i = 2; file_exists("{$dir}/{$file}"); $i++) {
            $file = "{$base}-{$i}.{$ext}";
        }
        $this->store($upload['tmp_name'], "{$dir}/{$file}", $ext);
        return "icons/{$file}";
    }

    public function deleteIcon(string $file): void
    {
        $file = basename($file);
        $path = $this->config->iconsDir() . '/' . $file;
        if (!self::hasAllowedExtension($file) || !is_file($path)) {
            throw new InvalidArgumentException('Icoon niet gevonden.');
        }
        if (!unlink($path)) {
            throw new InvalidArgumentException('Verwijderen mislukt (schrijfrechten?).');
        }
    }

    /** Slaat een logo/favicon/achtergrond op met een unieke naam (voorkomt oude versies in caches). */
    public function uploadBranding(array $upload, string $slot): string
    {
        if (!in_array($slot, ['logo', 'favicon', 'background'], true)) {
            throw new InvalidArgumentException('Onbekend type afbeelding.');
        }
        $ext  = $this->checkUpload($upload, self::MAX_BRANDING_BYTES);
        $file = sprintf('%s-%s.%s', $slot, bin2hex(random_bytes(4)), $ext);
        $this->store($upload['tmp_name'], $this->config->brandingDir() . '/' . $file, $ext);
        return "branding/{$file}";
    }

    /** Slaat een in de achtergrond-editor gegenereerde SVG op (opgeschoond, net als uploads). */
    public function saveGeneratedBackground(string $svg): string
    {
        if ($svg === '' || strlen($svg) > 256 * 1024) {
            throw new InvalidArgumentException('Ongeldige achtergrond.');
        }
        $file = sprintf('background-%s.svg', bin2hex(random_bytes(4)));
        $target = $this->config->brandingDir() . '/' . $file;
        if (file_put_contents($target, self::sanitizeSvg($svg)) === false) {
            throw new InvalidArgumentException('Opslaan mislukt (schrijfrechten?).');
        }
        @chmod($target, 0664);
        return "branding/{$file}";
    }

    /** Verwijdert eerder geüploade huisstijlbestanden die niet meer in gebruik zijn. */
    public function pruneBranding(array $inUse): void
    {
        $dir = $this->config->brandingDir();
        foreach (scandir($dir) ?: [] as $file) {
            if (preg_match(self::BRANDING_UPLOAD_PATTERN, $file) && !in_array("branding/{$file}", $inUse, true)) {
                @unlink("{$dir}/{$file}");
            }
        }
    }

    private function checkUpload(array $upload, int $maxBytes): string
    {
        $error = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Bestand is te groot.',
                UPLOAD_ERR_NO_FILE => 'Geen bestand ontvangen.',
                default => 'Upload mislukt.',
            });
        }
        if (!is_uploaded_file($upload['tmp_name'])) {
            throw new InvalidArgumentException('Upload mislukt.');
        }

        $name = (string) $upload['name'];
        if (filesize($upload['tmp_name']) > $maxBytes) {
            throw new InvalidArgumentException(sprintf('"%s" is groter dan %d MB.', $name, $maxBytes / 1048576));
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONS, true)) {
            throw new InvalidArgumentException("\"{$name}\": alleen " . implode(', ', self::EXTENSIONS) . ' toegestaan.');
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $valid = in_array($mime, self::MIME_TYPES[$ext], true) && match ($ext) {
            'svg'   => true, // inhoud wordt bij het opslaan geparsed en opgeschoond
            'ico'   => file_get_contents($upload['tmp_name'], false, null, 0, 4) === "\0\0\1\0",
            default => getimagesize($upload['tmp_name']) !== false,
        };
        if (!$valid) {
            throw new InvalidArgumentException("\"{$name}\" is geen geldige afbeelding.");
        }
        return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    private function store(string $tmp, string $target, string $ext): void
    {
        $ok = $ext === 'svg'
            ? file_put_contents($target, self::sanitizeSvg((string) file_get_contents($tmp))) !== false
            : move_uploaded_file($tmp, $target);
        if (!$ok) {
            throw new InvalidArgumentException('Opslaan mislukt (schrijfrechten?).');
        }
        @chmod($target, 0664);
    }

    /** Verwijdert scripts, event-handlers en externe/javascript-links uit een SVG. */
    public static function sanitizeSvg(string $svg): string
    {
        $doc = new DOMDocument();
        $loaded = @$doc->loadXML($svg, LIBXML_NONET | LIBXML_COMPACT);
        if (!$loaded || $doc->documentElement?->localName !== 'svg') {
            throw new InvalidArgumentException('Ongeldig SVG-bestand.');
        }

        $xpath = new DOMXPath($doc);
        foreach (iterator_to_array($xpath->query('//*[local-name()="script" or local-name()="foreignObject" or local-name()="iframe" or local-name()="embed" or local-name()="object"]')) as $node) {
            $node->parentNode?->removeChild($node);
        }
        foreach ($xpath->query('//*') as $element) {
            /** @var DOMElement $element */
            foreach (iterator_to_array($element->attributes) as $attr) {
                $name  = strtolower($attr->localName);
                $value = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $attr->value));
                $badLink = in_array($name, ['href', 'src'], true) && !str_starts_with($value, '#') && !str_starts_with($value, 'data:image/');
                if (str_starts_with($name, 'on') || $badLink || str_contains($value, 'javascript:')) {
                    $element->removeAttributeNode($attr);
                }
            }
        }
        return (string) $doc->saveXML();
    }

    private static function hasAllowedExtension(string $file): bool
    {
        return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::EXTENSIONS, true)
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $file) === 1;
    }

    private static function slug(string $name): string
    {
        $name = strtolower((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $name));
        return trim(substr($name, 0, 60), '-.');
    }
}
