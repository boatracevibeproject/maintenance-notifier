<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier;

use RuntimeException;

/**
 * ニュース一覧ページ（/owpc/pc/site/news/）を NewsItem の配列にする。
 *
 * 一覧の 1 行はこの形:
 *
 *   <ul class="news4_newsList">
 *    <li> <a href="/owpc/pc/site/news/2026/06/50343/">
 *      <div class="news4_dete_title">
 *       <p class="news4_date">
 *        <span class="news4_dateText"> 2026/06/29 </span>
 *        <span class="news4_dateLabels">
 *         <span class="label1 is-new">NEW</span>
 *         <span class="label1 is-pickup">ニュース</span>
 *        </span>
 *       </p>
 *       <p class="news4_newsTitle">【BOATCAST】システムメンテナンスに伴うサービス停止のお知らせ</p>
 *      </div> <img ...>
 *     </a> </li>
 *
 * @author shimomo
 */
final class NewsListParser
{
    private const string BASE_URL = 'https://www.boatrace.jp';

    /**
     * @return list<NewsItem>
     *
     * @throws RuntimeException 一覧の <ul> ごと見つからないとき（＝ページ構造が変わったか
     *                          メンテナンス中でエラーページが返っているか）
     */
    public function parse(string $html): array
    {
        $xpath = Html::xpath($html);

        $rows = $xpath->query('//ul[contains(concat(" ", normalize-space(@class), " "), " news4_newsList ")]/li');

        if ($rows === false || $rows->length === 0) {
            throw new RuntimeException(
                'ニュース一覧（ul.news4_newsList）が見つかりませんでした。'
                . 'ページ構造が変わったか、boatrace.jp がエラーページを返しています。',
            );
        }

        $items = [];

        foreach ($rows as $row) {
            $href = Html::text($xpath->query('.//a/@href', $row)?->item(0));

            if ($href === '') {
                continue;
            }

            $labels = [];

            foreach ($xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " label1 ")]', $row) ?: [] as $label) {
                $text = Html::text($label);

                // 「NEW」は鮮度バッジであってカテゴリではないので落とす。
                if ($text !== '' && $text !== 'NEW') {
                    $labels[] = $text;
                }
            }

            $items[] = new NewsItem(
                url: $this->absolutize($href),
                title: Html::text($xpath->query('.//p[contains(concat(" ", normalize-space(@class), " "), " news4_newsTitle ")]', $row)?->item(0)),
                publishedOn: Html::text($xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " news4_dateText ")]', $row)?->item(0)),
                labels: array_values(array_unique($labels)),
            );
        }

        return $items;
    }

    private function absolutize(string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        return self::BASE_URL . '/' . ltrim($href, '/');
    }
}
