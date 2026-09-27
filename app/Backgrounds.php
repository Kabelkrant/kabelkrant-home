<?php
declare(strict_types=1);

namespace App;

defined('APP_ROOT') || exit;

use InvalidArgumentException;

/**
 * Achtergrondpatronen die uit de instellingen worden opgebouwd; er wordt geen
 * bestand opgeslagen, de site haalt de SVG op via /?bg=<hash>.
 *
 * Soorten, velden, standaardwaarden en vormen staan in backgrounds.json. Dat
 * bestand gaat ook naar het beheer, waar public/assets/js/backgrounds.js
 * dezelfde SVG maakt voor de live preview. Houd de sjablonen hieronder en daar
 * gelijk.
 *
 * De patronen zijn afgeleid van de gratis set "Free SVG Backgrounds and Patterns"
 * van Matt Visiwig / SVGBackgrounds.com; die licentie vraagt om naamsvermelding,
 * die als opmerking in elke SVG staat.
 */
final class Backgrounds
{
    private static ?array $definitions = null;

    /** @return array{set_url: string, types: list<array>} */
    public static function definitions(): array
    {
        return self::$definitions ??= json_decode(
            (string) file_get_contents(__DIR__ . '/backgrounds.json'), true, 512, JSON_THROW_ON_ERROR,
        );
    }

    public static function find(string $id): ?array
    {
        foreach (self::definitions()['types'] as $type) {
            if ($type['id'] === $id) {
                return $type;
            }
        }
        return null;
    }

    /**
     * Controleert een patroon aan de hand van de velden van de soort.
     * Ontbrekende parameters krijgen de standaardwaarde, onbekende vervallen.
     *
     * @return array{type: string, params: array<string, string|int|float|bool>}
     * @throws InvalidArgumentException
     */
    public static function validate(mixed $pattern): array
    {
        $type = is_array($pattern) ? self::find((string) ($pattern['type'] ?? '')) : null;
        if ($type === null) {
            throw new InvalidArgumentException('Onbekend achtergrondpatroon.');
        }
        $in = is_array($pattern['params'] ?? null) ? $pattern['params'] : [];
        $params = [];
        foreach ($type['fields'] as $field) {
            $key = $field['key'];
            $value = $in[$key] ?? $type['defaults'][$key];
            $params[$key] = match ($field['type']) {
                'color' => is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value)
                    ? strtolower($value) : throw new InvalidArgumentException("Ongeldige kleur voor \"{$field['label']}\"."),
                'check' => (bool) $value,
                'range' => (is_int($value) || is_float($value)) && $value >= $field['min'] && $value <= $field['max']
                    ? $value : throw new InvalidArgumentException("Ongeldige waarde voor \"{$field['label']}\"."),
            };
        }
        return ['type' => $type['id'], 'params' => $params];
    }

    /** Korte, stabiele sleutel voor de URL: verandert met elke instelling, dus onbeperkt te cachen. */
    public static function hash(array $pattern): string
    {
        return substr(sha1(json_encode([$pattern['type'], $pattern['params']])), 0, 12);
    }

    /** Kleur waarmee de pagina gevuld wordt zolang de SVG nog niet geladen is. */
    public static function base(array $pattern): string
    {
        return $pattern['params'][self::find($pattern['type'])['base']];
    }

    /** Complete SVG die het hele scherm vult (background-size: cover). */
    public static function svg(array $pattern): string
    {
        $type = self::find($pattern['type']);
        $p = $pattern['params'];
        $head = self::open(match ($type['id']) {
            'liquid-cheese'    => '0 0 1600 800',
            'quantum-gradient' => '0 0 1200 800',
            'rose-petals'      => '0 0 800 400',
        }) . sprintf('<!-- "%s" by Matt Visiwig, SVGBackgrounds.com: %s -->', $type['name'], self::definitions()['set_url']);

        return $head . match ($type['id']) {
            'liquid-cheese'    => self::liquidCheese($p, $type['paths']),
            'quantum-gradient' => self::quantumGradient($p),
            'rose-petals'      => self::rosePetals($p),
        } . '</svg>';
    }

    private static function liquidCheese(array $p, array $paths): string
    {
        $n = count($paths);
        $svg = '';
        foreach ($paths as $i => $d) {
            $svg .= sprintf('<path fill="%s" d="%s"/>', self::mix($p['from'], $p['to'], ($i + 1) / $n), $d);
        }
        $flip = $p['flip'] ? ' transform="matrix(-1 0 0 1 1600 0)"' : '';
        return "<rect fill=\"{$p['from']}\" width=\"1600\" height=\"800\"/><g{$flip}>{$svg}</g>";
    }

    private static function quantumGradient(array $p): string
    {
        $n = (int) $p['bands'];
        $w = 1200 / $n;
        $defs = '';
        $rects = '';
        for ($k = 0; $k < $n; $k++) {
            $t = $k / ($n - 1);
            $x = $k * $w;
            $defs .= sprintf(
                '<linearGradient id="g%d" gradientUnits="userSpaceOnUse" x1="%3$s" y1="25" x2="%3$s" y2="777">'
                . '<stop offset="0" stop-color="%s"/><stop offset="1" stop-color="%s"/></linearGradient>',
                $k, self::num($x + 50), self::mix($p['tl'], $p['tr'], $t), self::mix($p['bl'], $p['br'], $t),
            );
            $rects .= sprintf('<rect fill="url(#g%d)" x="%s" width="%s" height="800"/>', $k, self::num($x), self::num(1200 - $x));
        }
        return "<rect fill=\"{$p['tl']}\" width=\"1200\" height=\"800\"/><defs>{$defs}</defs><g>{$rects}</g>";
    }

    private static function rosePetals(array $p): string
    {
        return "<rect fill=\"{$p['bg']}\" width=\"800\" height=\"400\"/><defs>"
            . "<radialGradient id=\"a\" cx=\"396\" cy=\"281\" r=\"514\" gradientUnits=\"userSpaceOnUse\"><stop offset=\"0\" stop-color=\"{$p['glow']}\"/><stop offset=\"1\" stop-color=\"{$p['bg']}\"/></radialGradient>"
            . "<linearGradient id=\"b\" gradientUnits=\"userSpaceOnUse\" x1=\"400\" y1=\"148\" x2=\"400\" y2=\"333\"><stop offset=\"0\" stop-color=\"{$p['petals']}\" stop-opacity=\"0\"/><stop offset=\"1\" stop-color=\"{$p['petals']}\" stop-opacity=\"0.5\"/></linearGradient>"
            . '</defs><rect fill="url(#a)" width="800" height="400"/><g fill-opacity="' . self::num($p['opacity'] / 100) . '">'
            . '<circle fill="url(#b)" cx="267.5" cy="61" r="300"/><circle fill="url(#b)" cx="532.5" cy="61" r="300"/><circle fill="url(#b)" cx="400" cy="30" r="300"/></g>';
    }

    private static function open(string $viewBox): string
    {
        return "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"100%\" height=\"100%\" viewBox=\"{$viewBox}\" preserveAspectRatio=\"xMidYMid slice\">";
    }

    /** Lineaire menging van twee #rrggbb-kleuren; t = 0 geeft a, t = 1 geeft b. */
    private static function mix(string $a, string $b, float $t): string
    {
        $out = '#';
        for ($i = 1; $i < 7; $i += 2) {
            $from = hexdec(substr($a, $i, 2));
            $to = hexdec(substr($b, $i, 2));
            $out .= sprintf('%02x', max(0, min(255, (int) round($from + ($to - $from) * $t))));
        }
        return $out;
    }

    /** Getal met hooguit twee decimalen, zonder overbodige nullen (zoals in backgrounds.js). */
    private static function num(float|int $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
