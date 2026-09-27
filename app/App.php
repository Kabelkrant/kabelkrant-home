<?php
declare(strict_types=1);

namespace App;

defined('APP_ROOT') || exit;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class App
{
    private readonly Auth $auth;
    private readonly Bookmarks $bookmarks;
    private readonly Settings $settings;
    private readonly Media $media;
    private readonly Updater $updater;

    public function __construct(private readonly Config $config)
    {
        $store = new JsonStore($config->dataDir);
        $this->auth      = new Auth($config, $store);
        $this->bookmarks = new Bookmarks($store);
        $this->settings  = new Settings($store);
        $this->media     = new Media($config);
        $this->updater   = new Updater($config, $store);
    }

    public function run(): void
    {
        if (isset($_GET['bg'])) {
            $this->background((string) $_GET['bg']);
        }

        // Pagina's zijn persoonlijk (sessie): nooit laten cachen door Varnish/browser.
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');

        if (isset($_GET['api'])) {
            $this->api((string) $_GET['api']);
            return;
        }

        match ($_GET['p'] ?? 'home') {
            'logout' => $this->logout(),
            'admin'  => $this->admin(),
            default  => $this->home(),
        };
    }

    /**
     * Levert de patroon-achtergrond als SVG, opgebouwd uit de instellingen. De hash in
     * de URL verandert met elke instelling, dus bij een juiste hash mag er onbeperkt
     * gecachet worden. Openbaar, net als de achtergrond op het inlogscherm.
     */
    private function background(string $hash): never
    {
        $settings = $this->settings->all();
        if ($settings['background_mode'] !== 'pattern') {
            http_response_code(404);
            exit;
        }
        $pattern = $settings['background_pattern'];
        $current = $hash === Backgrounds::hash($pattern);
        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Cache-Control: ' . ($current ? 'public, max-age=31536000, immutable' : 'no-cache'));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
        echo Backgrounds::svg($pattern);
        exit;
    }

    private function home(): void
    {
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$this->auth->check()) {
            if ($this->auth->attempt((string) ($_POST['password'] ?? ''), isset($_POST['remember']))) {
                $this->redirect(($_POST['return'] ?? '') === 'admin' ? './?p=admin' : './');
            }
            sleep(1); // remt het raden van wachtwoorden af
            $error = 'Onjuist wachtwoord.';
        }

        View::render('home', [
            'settings'      => $this->settings->all(),
            'authenticated' => $this->auth->check(),
            'bookmarks'     => $this->auth->check() ? $this->bookmarks->all() : [],
            'error'         => $error,
            'return'        => (string) ($_GET['return'] ?? ''),
        ]);
    }

    private function admin(): void
    {
        if (!$this->auth->check()) {
            $this->redirect('./?return=admin');
        }
        View::render('admin', [
            'settings' => $this->settings->all(),
            'boot'     => [
                'tree'     => $this->bookmarks->all(),
                'icons'    => $this->media->listIcons(),
                'settings' => $this->settings->all(),
                'defaults' => Settings::DEFAULTS,
                'backgrounds' => Backgrounds::definitions(),
                'csrf'     => $this->auth->csrfToken(),
                'limits'   => ['icon' => Media::MAX_ICON_BYTES, 'branding' => Media::MAX_BRANDING_BYTES, 'password' => Auth::MIN_LENGTH],
            ],
        ]);
    }

    private function logout(): void
    {
        $this->auth->logout();
        $this->redirect('./');
    }

    private function api(string $action): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->auth->check()) {
            $this->json(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
        }

        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        if ($isPost && !$this->auth->verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            $this->json(['ok' => false, 'error' => 'Sessie verlopen, herlaad de pagina.'], 403);
        }

        try {
            $result = match ([$isPost ? 'POST' : 'GET', $action]) {
                ['GET', 'bookmarks']       => ['tree' => $this->bookmarks->all()],
                ['POST', 'bookmarks.save'] => ['tree' => $this->bookmarks->save($this->jsonBody()['tree'] ?? throw new InvalidArgumentException('Ongeldige data.'))],
                ['GET', 'icons']           => ['icons' => $this->media->listIcons()],
                ['POST', 'icons.upload']   => $this->uploadIcons(),
                ['POST', 'icons.delete']   => $this->deleteIcon(),
                ['POST', 'branding.upload'] => ['path' => $this->media->uploadBranding($_FILES['file'] ?? [], (string) ($_POST['slot'] ?? ''))],
                ['POST', 'settings.save']  => $this->saveSettings(),
                ['POST', 'password.change'] => $this->changePassword(),
                ['GET', 'update.status']   => $this->updater->status(),
                ['POST', 'update.run']     => $this->updater->run(),
                default => $this->json(['ok' => false, 'error' => 'Onbekende actie.'], 404),
            };
        } catch (InvalidArgumentException | JsonException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }

        $this->json(['ok' => true] + $result);
    }

    private function uploadIcons(): array
    {
        $files = self::normalizeFiles($_FILES['files'] ?? []);
        if ($files === []) {
            throw new InvalidArgumentException('Geen bestanden ontvangen (te groot?).');
        }
        $uploaded = [];
        $errors = [];
        foreach ($files as $file) {
            try {
                $uploaded[] = $this->media->uploadIcon($file);
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }
        return ['uploaded' => $uploaded, 'errors' => $errors, 'icons' => $this->media->listIcons()];
    }

    private function deleteIcon(): array
    {
        $file = basename((string) ($this->jsonBody()['file'] ?? ''));
        if (in_array("icons/{$file}", Settings::referencedAssets($this->settings->all()), true)) {
            throw new InvalidArgumentException('Dit icoon is in gebruik als logo of favicon; kies daar eerst iets anders.');
        }
        $this->media->deleteIcon($file);
        $detached = $this->bookmarks->detachIcon("icons/{$file}");
        return ['detached' => $detached, 'tree' => $this->bookmarks->all(), 'icons' => $this->media->listIcons()];
    }

    private function saveSettings(): array
    {
        $settings = $this->settings->save($this->jsonBody(), $this->config->publicDir);
        $this->media->pruneBranding(Settings::referencedAssets($settings));
        return ['settings' => $settings];
    }

    private function changePassword(): array
    {
        $body = $this->jsonBody();
        $new  = (string) ($body['new'] ?? '');
        if ($new !== (string) ($body['repeat'] ?? '')) {
            throw new InvalidArgumentException('De nieuwe wachtwoorden komen niet overeen.');
        }
        $this->auth->changePassword((string) ($body['current'] ?? ''), $new);
        return ['csrf' => $this->auth->csrfToken()];
    }

    /** Zet $_FILES['x'] met meerdere bestanden om naar een lijst per bestand. */
    private static function normalizeFiles(array $files): array
    {
        if (!is_array($files['name'] ?? null)) {
            return $files === [] ? [] : [$files];
        }
        $list = [];
        foreach (array_keys($files['name']) as $i) {
            $list[] = array_combine(array_keys($files), array_column($files, $i));
        }
        return $list;
    }

    private function jsonBody(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Ongeldige data.');
        }
        return $data;
    }

    private function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location, true, 303);
        exit;
    }
}
