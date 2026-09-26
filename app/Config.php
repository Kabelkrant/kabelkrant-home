<?php
declare(strict_types=1);

namespace App;

defined('APP_ROOT') || exit;

final readonly class Config
{
    public function __construct(
        /** Beginwachtwoord uit .env; wordt genegeerd zodra er via het beheer een wachtwoord is ingesteld. */
        public ?string $password,
        public string $secretKey,
        public int $rememberDays,
        /** Map met de JSON-data; bewust buiten de repository en de webroot. */
        public string $dataDir,
        public string $publicDir,
    ) {}

    public static function fromEnvironment(): self
    {
        $password = getenv('PAGE_PASSWORD');
        $secret   = getenv('SECRET_KEY');

        if ($secret === false || $secret === '') {
            http_response_code(500);
            exit('Configuratiefout: SECRET_KEY ontbreekt in .env.');
        }

        return new self(
            password: $password === false || $password === '' ? null : $password,
            secretKey: $secret,
            rememberDays: (int) (getenv('REMEMBER_DAYS') ?: 30),
            dataDir: rtrim(getenv('DATA_DIR') ?: dirname(APP_ROOT) . '/data', '/'),
            publicDir: PUBLIC_ROOT,
        );
    }

    public function iconsDir(): string    { return $this->publicDir . '/icons'; }
    public function brandingDir(): string { return $this->publicDir . '/branding'; }
}
