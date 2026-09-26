<?php
declare(strict_types=1);

namespace App;

defined('APP_ROOT') || exit;

use InvalidArgumentException;

/**
 * Eén gedeeld wachtwoord met een optionele "onthouden"-cookie.
 *
 * Het wachtwoord staat als hash in auth.json (in te stellen via het beheer).
 * Zolang dat bestand ontbreekt, geldt PAGE_PASSWORD uit .env.
 *
 * Sessies en cookies zijn gekoppeld aan een vingerafdruk van het wachtwoord:
 * wijzig je het wachtwoord, dan worden alle andere logins automatisch ongeldig.
 */
final class Auth
{
    private const string REMEMBER_COOKIE = 'remember_auth';
    private const string DOCUMENT = 'auth';
    public const int MIN_LENGTH = 8;

    private ?string $hash = null;
    private bool $hashLoaded = false;

    public function __construct(private readonly Config $config, private readonly JsonStore $store)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'path'     => '/',
                'secure'   => self::isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        if (!$this->check() && $this->hasValidRememberCookie()) {
            $this->markAuthenticated();
        }
    }

    public function check(): bool
    {
        return !empty($_SESSION['authenticated'])
            && is_string($_SESSION['auth_fp'] ?? null)
            && hash_equals($this->fingerprint(), $_SESSION['auth_fp']);
    }

    public function attempt(string $password, bool $remember): bool
    {
        if (!$this->verify($password)) {
            return false;
        }
        session_regenerate_id(true);
        $this->markAuthenticated();
        if ($remember) {
            $this->setRememberCookie();
        }
        return true;
    }

    /** @throws InvalidArgumentException bij een fout huidig of ongeldig nieuw wachtwoord */
    public function changePassword(string $current, string $new): void
    {
        if (!$this->verify($current)) {
            throw new InvalidArgumentException('Het huidige wachtwoord klopt niet.');
        }
        if (mb_strlen($new) < self::MIN_LENGTH) {
            throw new InvalidArgumentException(sprintf('Het nieuwe wachtwoord moet minstens %d tekens lang zijn.', self::MIN_LENGTH));
        }
        if (strlen($new) > 4096) {
            throw new InvalidArgumentException('Het nieuwe wachtwoord is te lang.');
        }

        $this->hash = password_hash($new, PASSWORD_DEFAULT);
        $this->store->write(self::DOCUMENT, [
            'password_hash' => $this->hash,
            'changed_at'    => date(DATE_ATOM),
        ]);

        // Deze sessie blijft ingelogd; alle andere sessies en cookies vervallen.
        session_regenerate_id(true);
        $this->markAuthenticated();
        if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
            $this->setRememberCookie();
        }
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        $this->setCookie('', time() - 3600);
    }

    public function csrfToken(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public function verifyCsrf(?string $token): bool
    {
        return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    private function verify(string $password): bool
    {
        $hash = $this->storedHash();
        if ($hash !== null) {
            return password_verify($password, $hash);
        }
        return $this->config->password !== null && hash_equals($this->config->password, $password);
    }

    private function storedHash(): ?string
    {
        if (!$this->hashLoaded) {
            $data = $this->store->read(self::DOCUMENT, []);
            $this->hash = is_string($data['password_hash'] ?? null) ? $data['password_hash'] : null;
            $this->hashLoaded = true;
        }
        return $this->hash;
    }

    /** Verandert zodra het wachtwoord verandert (compatibel met de oude cookie zolang .env geldt). */
    private function fingerprint(): string
    {
        return hash_hmac('sha256', $this->storedHash() ?? (string) $this->config->password, $this->config->secretKey);
    }

    private function markAuthenticated(): void
    {
        $_SESSION['authenticated'] = true;
        $_SESSION['auth_fp'] = $this->fingerprint();
    }

    private function hasValidRememberCookie(): bool
    {
        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? '';
        return is_string($cookie) && $cookie !== '' && hash_equals($this->fingerprint(), $cookie);
    }

    private function setRememberCookie(): void
    {
        $this->setCookie($this->fingerprint(), time() + $this->config->rememberDays * 86400);
    }

    private function setCookie(string $value, int $expires): void
    {
        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function isHttps(): bool
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
