<?php
declare(strict_types=1);

namespace App;

defined('APP_ROOT') || exit;

use RuntimeException;

/** Leest en schrijft JSON-documenten in de datamap (atomair via tijdelijk bestand + rename). */
final readonly class JsonStore
{
    public function __construct(private string $dir) {}

    public function read(string $name, mixed $default = null): mixed
    {
        $file = $this->path($name);
        if (!is_readable($file)) {
            return $default;
        }
        $decoded = json_decode((string) file_get_contents($file), true);
        return $decoded ?? $default;
    }

    public function write(string $name, mixed $data): void
    {
        if (!is_dir($this->dir) && !mkdir($this->dir, 0770, true) && !is_dir($this->dir)) {
            throw new RuntimeException('Datamap kan niet worden aangemaakt.');
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $file = $this->path($name);
        $tmp  = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('Opslaan mislukt (schrijfrechten?).');
        }
    }

    private function path(string $name): string
    {
        return $this->dir . '/' . $name . '.json';
    }
}
