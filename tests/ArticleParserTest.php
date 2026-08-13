<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier\Tests;

use BVP\MaintenanceNotifier\ArticleParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArticleParserTest extends TestCase
{
    #[Test]
    public function 本文からメンテナンスの時間帯を取れる(): void
    {
        $body = (new ArticleParser())->parse(Fixture::get('article.html'));

        $this->assertNotNull($body);

        // ⭐️これが取りたい情報。時間帯は一覧の見出しには出てこない。
        $this->assertStringContainsString('2026年6月29日（月）24時頃～ 30日（火）5時頃まで', $body);
    }

    #[Test]
    public function brを改行として残す(): void
    {
        $body = (new ArticleParser())->parse(Fixture::get('article.html'));

        $this->assertNotNull($body);
        $this->assertStringContainsString("\n", $body);
        $this->assertStringStartsWith('システムメンテナンスのため、', $body);
    }

    #[Test]
    public function 消えた記事の案内ページではnullを返す(): void
    {
        // 古い記事は「不正なURLへのリクエストです。」に差し替わるが HTTP 200 で返る。
        // ステータスでは判別できないので、本文の有無で弾く。
        $this->assertNull((new ArticleParser())->parse(Fixture::get('article-invalid.html')));
    }
}
