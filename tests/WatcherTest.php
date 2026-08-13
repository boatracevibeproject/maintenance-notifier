<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier\Tests;

use BVP\MaintenanceNotifier\ArticleParser;
use BVP\MaintenanceNotifier\DiscordNotifier;
use BVP\MaintenanceNotifier\MaintenanceFilter;
use BVP\MaintenanceNotifier\NewsListParser;
use BVP\MaintenanceNotifier\SeenStore;
use BVP\MaintenanceNotifier\Tests\Doubles\FakeHttpClient;
use BVP\MaintenanceNotifier\Watcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WatcherTest extends TestCase
{
    private const string NEWS_URL = 'https://www.boatrace.jp/owpc/pc/site/news/';

    private const string ARTICLE_URL = 'https://www.boatrace.jp/owpc/pc/site/news/2026/06/50343/';

    private const string BANK_URL = 'https://www.boatrace.jp/owpc/pc/site/news/2026/06/50290/';

    private const string WEBHOOK_URL = 'https://discord.test/webhook';

    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/bvp-watcher-' . bin2hex(random_bytes(6)) . '/seen.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
            rmdir(dirname($this->path));
        }
    }

    #[Test]
    public function メンテナンス記事だけを本文つきで通知する(): void
    {
        $http = $this->http();
        $store = new SeenStore($this->path);

        $sent = $this->watcher($http, $store)->run();

        $this->assertSame(2, $sent);
        $this->assertCount(2, $http->posted);

        $embed = $http->posted[0]['payload']['embeds'][0];

        $this->assertSame(self::WEBHOOK_URL, $http->posted[0]['url']);
        $this->assertSame('【BOATCAST】システムメンテナンスに伴うサービス停止のお知らせ', $embed['title']);
        $this->assertSame(self::ARTICLE_URL, $embed['url']);
        $this->assertStringContainsString('24時頃～ 30日（火）5時頃まで', $embed['description']);
    }

    #[Test]
    public function 一度通知した記事は二度目に鳴らさない(): void
    {
        $store = new SeenStore($this->path);
        $this->watcher($this->http(), $store)->run();
        $store->save();

        $second = $this->http();
        $sent = $this->watcher($second, new SeenStore($this->path))->run();

        $this->assertSame(0, $sent);
        $this->assertSame([], $second->posted);
    }

    #[Test]
    public function bootstrapでは通知せず記録だけする(): void
    {
        $http = $this->http();
        $store = new SeenStore($this->path);

        $recorded = $this->watcher($http, $store)->run(bootstrap: true);

        $this->assertSame(2, $recorded);
        $this->assertSame([], $http->posted);
        $this->assertTrue($store->has(self::ARTICLE_URL));

        // 記事ページも取りに行かない（無駄なリクエストを boatrace.jp に投げない）。
        $this->assertSame([self::NEWS_URL], $http->requested);
    }

    #[Test]
    public function 本文が取れなくても通知はする(): void
    {
        // 記事ページだけ落ちるケース。見出しとリンクだけでも「メンテがある」は伝わる。
        $http = new FakeHttpClient(
            responses: [
                self::NEWS_URL => Fixture::get('news-list-with-maintenance.html'),
                self::BANK_URL => Fixture::get('article.html'),
            ],
            failing: [self::ARTICLE_URL],
        );

        $sent = $this->watcher($http, new SeenStore($this->path))->run();

        $this->assertSame(2, $sent);
        $this->assertArrayNotHasKey('description', $http->posted[0]['payload']['embeds'][0]);
    }

    #[Test]
    public function ドライランでは送らず記録もしない(): void
    {
        $http = $this->http();
        $store = new SeenStore($this->path);

        $this->watcher($http, $store)->run(dryRun: true);

        $this->assertSame([], $http->posted);
        $this->assertFalse($store->has(self::ARTICLE_URL));
    }

    private function http(): FakeHttpClient
    {
        return new FakeHttpClient([
            self::NEWS_URL => Fixture::get('news-list-with-maintenance.html'),
            self::ARTICLE_URL => Fixture::get('article.html'),
            self::BANK_URL => Fixture::get('article.html'),
        ]);
    }

    private function watcher(FakeHttpClient $http, SeenStore $store): Watcher
    {
        return new Watcher(
            http: $http,
            listParser: new NewsListParser(),
            articleParser: new ArticleParser(),
            filter: new MaintenanceFilter(),
            store: $store,
            notifier: new DiscordNotifier($http, self::WEBHOOK_URL),
            newsUrl: self::NEWS_URL,
            log: static function (string $message): void {},
            sendIntervalSeconds: 0,
        );
    }
}
