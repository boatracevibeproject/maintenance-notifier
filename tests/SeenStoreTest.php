<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier\Tests;

use BVP\MaintenanceNotifier\NewsItem;
use BVP\MaintenanceNotifier\SeenStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SeenStoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/bvp-seen-' . bin2hex(random_bytes(6)) . '/seen.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
            rmdir(dirname($this->path));
        }
    }

    #[Test]
    public function ファイルが無ければ初回とみなす(): void
    {
        $this->assertTrue((new SeenStore($this->path))->isFresh());
    }

    #[Test]
    public function 記録したものが次回は既知になる(): void
    {
        $item = new NewsItem('https://www.boatrace.jp/owpc/pc/site/news/2026/06/50343/', 'メンテナンスのお知らせ', '2026/06/29');

        $store = new SeenStore($this->path);
        $this->assertFalse($store->has($item->url));

        $store->remember($item, '2026-06-29T10:00:00+09:00');
        $this->assertTrue($store->save());

        $reloaded = new SeenStore($this->path);
        $this->assertFalse($reloaded->isFresh());
        $this->assertTrue($reloaded->has($item->url));
    }

    #[Test]
    public function 変化が無ければ書き直さない(): void
    {
        (new SeenStore($this->path))->save();

        $before = filemtime($this->path);

        $this->assertFalse((new SeenStore($this->path))->save());
        $this->assertSame($before, filemtime($this->path));
    }

    #[Test]
    public function 壊れたJSONは例外にする(): void
    {
        // 黙って空の状態から再開すると、一覧に残っている記事を全部鳴らし直してしまう。
        mkdir(dirname($this->path), 0o755, true);
        file_put_contents($this->path, '{ 壊れている');

        $this->expectException(RuntimeException::class);

        new SeenStore($this->path);
    }
}
