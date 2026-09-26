<?php
declare(strict_types=1);

defined('APP_ROOT') || exit;

if (PHP_VERSION_ID < 80400) {
    http_response_code(500);
    exit('Deze applicatie vereist PHP 8.4 of nieuwer.');
}

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = APP_ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

/**
 * Leest KEY=value-regels uit een .env-bestand in de omgeving,
 * zonder al gezette variabelen te overschrijven.
 */
function load_env_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
}

// Geheimen staan buiten de repository in ~/.env, zodat ze niet in git belanden.
load_env_file(dirname(APP_ROOT) . '/.env');
