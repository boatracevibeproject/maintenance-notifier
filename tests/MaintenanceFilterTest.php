<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier\Tests;

use BVP\MaintenanceNotifier\MaintenanceFilter;
use BVP\MaintenanceNotifier\NewsItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MaintenanceFilterTest extends TestCase
{
    /**
     * 実際に boatrace.jp に出た見出し（拾いたいもの）。
     *
     * @return iterable<string, array{string}>
     */
    public static function 拾う見出し(): iterable
    {
        yield 'BOATCAST' => ['【BOATCAST】システムメンテナンスに伴うサービス停止のお知らせ'];
        yield 'システムメンテナンス' => ['システムメンテナンスの実施について'];
        yield '銀行メンテ' => ['三井住友銀行メンテナンスについて'];
        yield 'システムエラー' => ['システムエラー'];
        yield '半角カナ' => ['ｼｽﾃﾑﾒﾝﾃﾅﾝｽのお知らせ'];
    }

    /**
     * 一覧に並ぶふつうのニュース（拾いたくないもの）。
     *
     * @return iterable<string, array{string}>
     */
    public static function 拾わない見出し(): iterable
    {
        yield '発売日程' => ['第72回ボートレースメモリアル 場間場外発売日程'];
        yield '優勝' => ['SG第31回オーシャンカップ（びわこ）は定松勇樹選手（5121）が優勝いたしました'];
        yield '選手募集' => ['第142期ボートレーサー募集中'];
        yield '障害者は誤爆させない' => ['障害者スポーツ支援のお知らせ'];
        yield 'レース中断は開催情報' => ['荒天によるレース中断について'];
    }

    #[Test]
    #[DataProvider('拾う見出し')]
    public function メンテナンス系を拾う(string $title): void
    {
        $this->assertTrue((new MaintenanceFilter())->matches(new NewsItem('https://example.test/', $title)));
    }

    #[Test]
    #[DataProvider('拾わない見出し')]
    public function ふつうのニュースは拾わない(string $title): void
    {
        $this->assertFalse((new MaintenanceFilter())->matches(new NewsItem('https://example.test/', $title)));
    }

    #[Test]
    public function 実際の一覧を通すと拾うのはメンテナンス記事だけ(): void
    {
        $filter = new MaintenanceFilter();
        $items = (new \BVP\MaintenanceNotifier\NewsListParser())->parse(Fixture::get('news-list-with-maintenance.html'));

        $matched = array_map(
            static fn (NewsItem $item): string => $item->title,
            array_values(array_filter($items, static fn (NewsItem $item): bool => $filter->matches($item))),
        );

        $this->assertSame([
            '【BOATCAST】システムメンテナンスに伴うサービス停止のお知らせ',
            '三井住友銀行メンテナンスについて',
        ], $matched);
    }

    #[Test]
    public function 実データの一覧では1件も誤爆しない(): void
    {
        // メンテナンス記事は数ヶ月に 1 本。誤爆が無いことを実データの 1 ヶ月ぶんで確かめておく。
        $filter = new MaintenanceFilter();
        $items = (new \BVP\MaintenanceNotifier\NewsListParser())->parse(Fixture::get('news-list.html'));

        $this->assertSame([], array_values(array_filter($items, static fn (NewsItem $item): bool => $filter->matches($item))));
    }
}
