<?php
declare(strict_types=1);

namespace App;

defined('APP_ROOT') || exit;

use InvalidArgumentException;

/**
 * Vormgeving en indeling van de startpagina, opgeslagen als settings.json.
 * Ontbrekende sleutels vallen terug op DEFAULTS, zodat nieuwe opties
 * zonder migratie kunnen worden toegevoegd.
 */
final readonly class Settings
{
    private const string DOCUMENT = 'settings';

    public const array DEFAULTS = [
        'name'             => 'Kabelkrant Home',
        'logo'             => 'branding/dino.svg',
        'favicon'          => '',            // leeg = logo gebruiken
        'background_image' => 'branding/background-waves.svg', // leeg = alleen kleur
        'logo_animation'   => true,          // springen + geluid bij klikken op het logo
        'background_pattern' => null,        // laatste instellingen van de achtergrond-editor
        'colors' => [
            'background' => '#c4e2d8',
            'text'       => '#1f1f1f',
            'link'       => '#1f1f1f',
            'link_hover' => '#1f1f1f',
            'accent'     => '#1f7755',
        ],
        'layout' => [
            'columns'       => 1,
            'logo_position' => 'left',       // left | top
        ],
    ];

    public const array LOGO_POSITIONS = ['left', 'top'];
    public const array PATTERN_TYPES = [
        'liquid-cheese', 'bullseye-gradient', 'hollowed-boxes', 'quantum-gradient', 'rose-petals', 'wintery-sunburst',
    ];
    public const array COLUMNS = [1, 2, 3];

    public function __construct(private JsonStore $store) {}

    public function all(): array
    {
        $stored = $this->store->read(self::DOCUMENT, []);
        return array_replace_recursive(self::DEFAULTS, is_array($stored) ? $stored : []);
    }

    /** @throws InvalidArgumentException bij ongeldige data */
    public function save(array $input, string $publicDir): array
    {
        $settings = self::validate($input, $publicDir);
        // De achtergrond-editor heeft een eigen opslagactie; die instellingen blijven behouden.
        $settings['background_pattern'] = $this->all()['background_pattern'];
        $this->store->write(self::DOCUMENT, $settings);
        return $settings;
    }

    /** Maakt een gegenereerde patroon-achtergrond actief en onthoudt de editor-instellingen. */
    public function saveBackgroundPattern(array $pattern, string $file, string $baseColor): array
    {
        if (!preg_match('/^#[0-9a-f]{6}$/', $baseColor)) {
            throw new InvalidArgumentException('Ongeldige basiskleur.');
        }
        $settings = $this->all();
        $settings['background_image'] = $file;
        $settings['background_pattern'] = self::validatePattern($pattern) + ['file' => $file];
        $settings['colors']['background'] = $baseColor;
        $this->store->write(self::DOCUMENT, $settings);
        return $settings;
    }

    /** @return array{type: string, params: array<string, string|int|float|bool>} */
    public static function validatePattern(array $pattern): array
    {
        $type = (string) ($pattern['type'] ?? '');
        if (!in_array($type, self::PATTERN_TYPES, true)) {
            throw new InvalidArgumentException('Onbekend achtergrondpatroon.');
        }
        $params = is_array($pattern['params'] ?? null) ? $pattern['params'] : [];
        if (count($params) > 16) {
            throw new InvalidArgumentException('Te veel parameters.');
        }
        $clean = [];
        foreach ($params as $key => $value) {
            $valid = is_string($key) && preg_match('/^[a-z_]{1,20}$/', $key) && match (true) {
                is_bool($value)                    => true,
                is_int($value), is_float($value)   => abs($value) <= 10000,
                is_string($value)                  => preg_match('/^#[0-9a-f]{6}$/', $value) === 1,
                default                            => false,
            };
            if (!$valid) {
                throw new InvalidArgumentException('Ongeldige parameter voor het patroon.');
            }
            $clean[$key] = $value;
        }
        return ['type' => $type, 'params' => $clean];
    }

    public static function validate(array $in, string $publicDir): array
    {
        $d = self::DEFAULTS;

        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            throw new InvalidArgumentException('Geef een naam op van maximaal 80 tekens.');
        }

        $colors = [];
        foreach ($d['colors'] as $key => $default) {
            $value = strtolower(trim((string) ($in['colors'][$key] ?? $default)));
            if (!preg_match('/^#[0-9a-f]{6}$/', $value)) {
                throw new InvalidArgumentException("Ongeldige kleur voor \"{$key}\".");
            }
            $colors[$key] = $value;
        }

        $columns = (int) ($in['layout']['columns'] ?? 1);
        if (!in_array($columns, self::COLUMNS, true)) {
            throw new InvalidArgumentException('Kies 1, 2 of 3 kolommen.');
        }
        $position = (string) ($in['layout']['logo_position'] ?? 'left');
        if (!in_array($position, self::LOGO_POSITIONS, true)) {
            throw new InvalidArgumentException('Ongeldige logopositie.');
        }

        $logo = self::validateAsset((string) ($in['logo'] ?? ''), $publicDir, 'logo');
        if ($logo === '') {
            throw new InvalidArgumentException('Een logo is verplicht.');
        }

        return [
            'name'             => $name,
            'logo'             => $logo,
            'favicon'          => self::validateAsset((string) ($in['favicon'] ?? ''), $publicDir, 'favicon'),
            'background_image' => self::validateAsset((string) ($in['background_image'] ?? ''), $publicDir, 'achtergrond'),
            'logo_animation'   => (bool) ($in['logo_animation'] ?? false),
            'colors'           => $colors,
            'layout'           => ['columns' => $columns, 'logo_position' => $position],
        ];
    }

    /** Alleen bestaande bestanden uit branding/ of icons/ zijn toegestaan. */
    private static function validateAsset(string $path, string $publicDir, string $what): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (!preg_match('#^(branding|icons)/[A-Za-z0-9][A-Za-z0-9._-]*$#', $path) || !is_file($publicDir . '/' . $path)) {
            throw new InvalidArgumentException("Bestand voor {$what} niet gevonden.");
        }
        return $path;
    }

    /** Alle paden die door deze instellingen worden gebruikt. */
    public static function referencedAssets(array $settings): array
    {
        return array_values(array_filter([$settings['logo'], $settings['favicon'], $settings['background_image']]));
    }
}
