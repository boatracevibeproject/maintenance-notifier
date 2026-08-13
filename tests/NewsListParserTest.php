<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier\Tests;

use BVP\MaintenanceNotifier\NewsListParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NewsListParserTest extends TestCase
{
    #[Test]
    public function 実際の一覧ページを読める(): void
    {
        $items = (new NewsListParser())->parse(Fixture::get('news-list.html'));

        // 一覧は 1 ページ 20 件。
        $this->assertCount(20, $items);

        $first = $items[0];

        $this->assertSame('https://www.boatrace.jp/owpc/pc/site/news/2026/08/51185/', $first->url);
        $this->assertSame('第72回ボートレースメモリアル 場間場外発売日程', $first->title);
        $this->assertSame('2026/08/13', $first->publishedOn);

        // 「NEW」は鮮度バッジなのでカテゴリには混ぜない。
        $this->assertSame(['ニュース'], $first->labels);
    }

    #[Test]
    public function 相対リンクを絶対URLにする(): void
    {
        $items = (new NewsListParser())->parse(Fixture::get('news-list-with-maintenance.html'));

        $this->assertSame('https://www.boatrace.jp/owpc/pc/site/news/2026/06/50343/', $items[0]->url);
    }

    #[Test]
    public function 外部リンクはそのまま持つ(): void
    {
        $items = (new NewsListParser())->parse(Fixture::get('news-list-with-maintenance.html'));

        $this->assertSame('https://www.boatrace-pr.jp/pc/site/release/2026/06/11556/', $items[3]->url);
    }

    #[Test]
    public function 複数のカテゴリを持つ行を読める(): void
    {
        $items = (new NewsListParser())->parse(Fixture::get('news-list-with-maintenance.html'));

        $this->assertSame(['ニュース', 'テレボート'], $items[2]->labels);
    }

    #[Test]
    public function 全角スペース区切りの見出しが壊れない(): void
    {
        $items = (new NewsListParser())->parse(Fixture::get('news-list-with-maintenance.html'));

        $this->assertSame('第72回ボートレースメモリアル ドリーム戦出場選手発表', $items[1]->title);
    }

    #[Test]
    public function 一覧が無いページでは例外を投げる(): void
    {
        // 記事が消えたときの案内ページ。HTTP 200 で返ってくるので、
        // ここで落ちること自体が「構造が変わった／メンテ中」の検知になる。
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/ニュース一覧/');

        (new NewsListParser())->parse(Fixture::get('article-invalid.html'));
    }
}
