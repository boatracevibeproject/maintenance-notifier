<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier\Tests;

use RuntimeException;

/**
 * tests/Fixtures/ の HTML を読む。news-list.html と article.html は
 * boatrace.jp から実際に取得したものをそのまま置いてある。
 *
 * @author shimomo
 */
final class Fixture
{
    public static function get(string $name): string
    {
        $path = __DIR__ . '/Fixtures/' . $name;
        $html = is_file($path) ? file_get_contents($path) : false;

        if ($html === false) {
            throw new RuntimeException("フィクスチャを読めませんでした: {$path}");
        }

        return $html;
    }
}
