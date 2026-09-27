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
        'background_mode'  => 'image',       // image | pattern | color
        'background_image' => 'branding/background-waves.svg',
        'background_pattern' => null,        // {type, params} uit de achtergrond-editor; SVG via /?bg=
        'logo_animation'   => false,         // springen + geluid bij klikken op het logo
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
    public const array BACKGROUND_MODES = ['image', 'pattern', 'color'];
    public const array COLUMNS = [1, 2, 3];

    public function __construct(private JsonStore $store) {}

    public function all(): array
    {
        $stored = $this->store->read(self::DOCUMENT, []);
        $stored = is_array($stored) ? self::migrate($stored) : [];
        return array_replace_recursive(self::DEFAULTS, $stored);
    }

    /** @throws InvalidArgumentException bij ongeldige data */
    public function save(array $input, string $publicDir): array
    {
        // Zonder nieuw patroon blijven de laatste instellingen van de achtergrond-editor behouden.
        $input['background_pattern'] ??= $this->all()['background_pattern'];
        $settings = self::validate($input, $publicDir);
        $this->store->write(self::DOCUMENT, $settings);
        return $settings;
    }

    /**
     * Zet instellingen uit oudere versies om. Tot september 2026 werd een patroon als
     * bestand in branding/ opgeslagen (background_pattern.file); dat bestand wordt bij
     * de volgende keer opslaan vanzelf opgeruimd.
     */
    private static function migrate(array $s): array
    {
        if (!isset($s['background_mode'])) {
            $file = $s['background_pattern']['file'] ?? null;
            $s['background_mode'] = match (true) {
                $file !== null && $file === ($s['background_image'] ?? null) => 'pattern',
                ($s['background_image'] ?? null) === ''                     => 'color',
                default                                                     => 'image',
            };
            if ($s['background_mode'] === 'pattern') {
                $s['background_image'] = self::DEFAULTS['background_image'];
            }
        }
        if (isset($s['background_pattern'])) {
            try {
                $s['background_pattern'] = Backgrounds::validate($s['background_pattern']);
            } catch (InvalidArgumentException) {
                $s['background_pattern'] = null; // patroonsoort bestaat niet meer
            }
        }
        if ($s['background_mode'] === 'pattern' && empty($s['background_pattern'])) {
            $s['background_mode'] = 'image';
            $s['background_image'] = self::DEFAULTS['background_image'];
        }
        return $s;
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

        $mode = (string) ($in['background_mode'] ?? 'image');
        if (!in_array($mode, self::BACKGROUND_MODES, true)) {
            throw new InvalidArgumentException('Ongeldige soort achtergrond.');
        }
        $pattern = isset($in['background_pattern']) ? Backgrounds::validate($in['background_pattern']) : null;
        if ($mode === 'pattern') {
            if ($pattern === null) {
                throw new InvalidArgumentException('Kies een patroon.');
            }
            $colors['background'] = Backgrounds::base($pattern);
        }

        return [
            'name'             => $name,
            'logo'             => $logo,
            'favicon'          => self::validateAsset((string) ($in['favicon'] ?? ''), $publicDir, 'favicon'),
            'background_mode'  => $mode,
            'background_image' => self::validateAsset((string) ($in['background_image'] ?? ''), $publicDir, 'achtergrond'),
            'background_pattern' => $pattern,
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

    /** CSS-waarde voor background-image volgens de gekozen soort achtergrond. */
    public static function backgroundCss(array $settings): string
    {
        return match (true) {
            $settings['background_mode'] === 'pattern'
                => 'url("' . View::e(View::base() . '?bg=' . Backgrounds::hash($settings['background_pattern'])) . '")',
            $settings['background_mode'] === 'image' && $settings['background_image'] !== ''
                => 'url("' . View::asset($settings['background_image']) . '")',
            default => 'none',
        };
    }

    /** Alle paden die door deze instellingen worden gebruikt. */
    public static function referencedAssets(array $settings): array
    {
        return array_values(array_filter([$settings['logo'], $settings['favicon'], $settings['background_image']]));
    }
}
