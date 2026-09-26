<?php
declare(strict_types=1);

namespace App;

defined('APP_ROOT') || exit;

final class View
{
    public static function render(string $template, array $vars = []): void
    {
        extract($vars, EXTR_SKIP);
        require APP_ROOT . "/views/{$template}.php";
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Pad van de installatiemap vanaf de webroot, bijv. "/" of "/home/". */
    public static function base(): string
    {
        static $base = null;
        return $base ??= rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
    }

    /** Absolute URL naar een lokaal bestand, met wijzigingsdatum als cache-buster. */
    public static function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = PUBLIC_ROOT . '/' . $path;
        return self::e(self::base() . $path . (is_file($file) ? '?v=' . filemtime($file) : ''));
    }

    public static function jsonForScript(mixed $data): string
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function mimeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'svg'         => 'image/svg+xml',
            'ico'         => 'image/x-icon',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            default       => 'image/png',
        };
    }

    /** Rendert een lijst bookmarks/categorieën (recursief; uitgeschakelde items worden overgeslagen). */
    public static function bookmarkItems(array $items, bool $asList): void
    {
        foreach ($items as $item) {
            if (($item['enabled'] ?? true) === false) {
                continue;
            }
            if ($asList) {
                echo '<li>';
            }
            if (($item['type'] ?? 'link') === 'category') {
                $icon = $item['icon'] ?: 'icons/map.svg';
                echo '<details open><summary><img src="', self::e($icon), '" alt="">', self::e($item['label']), '</summary><ul>';
                self::bookmarkItems($item['items'] ?? [], true);
                echo '</ul></details>';
            } else {
                echo '<a href="', self::e($item['url']), '">';
                if (($item['icon'] ?? '') !== '') {
                    echo '<img src="', self::e($item['icon']), '" alt="">';
                }
                echo self::e($item['label']), '</a>';
            }
            if ($asList) {
                echo "</li>\n";
            }
        }
    }
}
