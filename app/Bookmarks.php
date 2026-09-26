<?php
declare(strict_types=1);

namespace App;

defined('APP_ROOT') || exit;

use InvalidArgumentException;

/**
 * Boom van categorieën en links (onbeperkt nestbaar), opgeslagen als bookmarks.json.
 *
 *   link:     { id, type: "link", label, url, icon, enabled?, column? }
 *   category: { id, type: "category", label, icon, items: [...], enabled?, column? }
 *
 * "column" (1-3) staat alleen op items op het hoogste niveau en bepaalt in welke
 * kolom van de startpagina ze staan.
 */
final readonly class Bookmarks
{
    private const string DOCUMENT = 'bookmarks';
    private const int MAX_DEPTH = 50;
    public const int MAX_COLUMNS = 3;

    public function __construct(private JsonStore $store) {}

    public function all(): array
    {
        $tree = $this->store->read(self::DOCUMENT, []);
        return is_array($tree) ? $tree : [];
    }

    /** @throws InvalidArgumentException bij ongeldige data */
    public function save(array $tree): array
    {
        $tree = self::validate($tree);
        $this->store->write(self::DOCUMENT, $tree);
        return $tree;
    }

    /** Verwijdert een icoonpad uit alle items; geeft het aantal aangepaste items terug. */
    public function detachIcon(string $iconPath): int
    {
        $count = 0;
        $walk = static function (array $nodes) use (&$walk, &$count, $iconPath): array {
            foreach ($nodes as &$node) {
                if (($node['icon'] ?? '') === $iconPath) {
                    $node['icon'] = '';
                    $count++;
                }
                if (($node['type'] ?? '') === 'category') {
                    $node['items'] = $walk($node['items'] ?? []);
                }
            }
            return $nodes;
        };

        $tree = $walk($this->all());
        if ($count > 0) {
            $this->store->write(self::DOCUMENT, $tree);
        }
        return $count;
    }

    /** Valideert en normaliseert de boom recursief. */
    public static function validate(array $nodes, int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException('Nesting te diep.');
        }

        $result = [];
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                throw new InvalidArgumentException('Ongeldig item in de boom.');
            }
            $id    = trim((string) ($node['id'] ?? ''));
            $label = trim((string) ($node['label'] ?? ''));
            $icon  = trim((string) ($node['icon'] ?? ''));

            if ($id === '' || $label === '') {
                throw new InvalidArgumentException('Elk item heeft een id en naam nodig.');
            }

            $out = match ($node['type'] ?? '') {
                'category' => [
                    'id'    => $id,
                    'type'  => 'category',
                    'label' => $label,
                    'icon'  => $icon,
                    'items' => self::validate(is_array($node['items'] ?? null) ? $node['items'] : [], $depth + 1),
                ],
                'link' => [
                    'id'    => $id,
                    'type'  => 'link',
                    'label' => $label,
                    'url'   => self::validateUrl((string) ($node['url'] ?? ''), $label),
                    'icon'  => $icon,
                ],
                default => throw new InvalidArgumentException('Onbekend itemtype.'),
            };

            if (($node['enabled'] ?? true) === false) {
                $out['enabled'] = false;
            }
            if ($depth === 0) {
                $out['column'] = max(1, min(self::MAX_COLUMNS, (int) ($node['column'] ?? 1)));
            }
            $result[] = $out;
        }
        return $result;
    }

    /**
     * Verdeelt de items op het hoogste niveau over $count kolommen.
     * Items in een kolom die niet (meer) bestaat, komen in de laatste kolom.
     *
     * @return list<list<array>>
     */
    public static function columns(array $tree, int $count): array
    {
        $columns = array_fill(0, $count, []);
        foreach ($tree as $node) {
            $index = max(1, min($count, (int) ($node['column'] ?? 1))) - 1;
            $columns[$index][] = $node;
        }
        return $columns;
    }

    private static function validateUrl(string $url, string $label): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new InvalidArgumentException("Bookmark \"{$label}\" heeft geen URL.");
        }
        // Voorkom javascript:/data:-links; al het andere (http, https, smb, rdp, …) mag.
        // Browsers negeren witruimte/stuurtekens in het schema, dus die eerst weghalen.
        $normalized = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $url));
        if (preg_match('/^(javascript|data|vbscript):/', $normalized)) {
            throw new InvalidArgumentException("Bookmark \"{$label}\" heeft een niet-toegestane URL.");
        }
        return $url;
    }
}
