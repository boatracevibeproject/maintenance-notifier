<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier;

/**
 * ニュース一覧の 1 行。
 *
 * 重複排除のキーは URL。記事 ID（/site/news/2026/06/50343/ の 50343）を使わないのは、
 * 一覧には boatrace-pr.jp や raceresult へ飛ぶ外部リンクも混ざっていて ID を持たない行が
 * あるため。URL なら全ての行で一意に取れる。
 *
 * @author shimomo
 */
final readonly class NewsItem
{
    /**
     * @param string        $url         絶対 URL
     * @param string        $title       見出し
     * @param string        $publishedOn 一覧に出ている日付（'2026/06/29'）。取れなければ空文字
     * @param list<string>  $labels      カテゴリ（'ニュース', 'テレボート' 等）。'NEW' は除外済み
     */
    public function __construct(
        public string $url,
        public string $title,
        public string $publishedOn = '',
        public array $labels = [],
    ) {}

    /**
     * boatrace.jp 自身の記事ページか（＝本文を取りに行けるか）。
     *
     * 外部リンクは相手のページ構造が読めないので本文取得の対象外にする。
     */
    public function hasArticlePage(): bool
    {
        return preg_match('#\Ahttps://www\.boatrace\.jp/owpc/pc/site/news/\d{4}/\d{2}/\d+/?\z#', $this->url) === 1;
    }
}
