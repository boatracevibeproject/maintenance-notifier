<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier;

/**
 * 記事ページ（/owpc/pc/site/news/2026/06/50343/）から本文を取り出す。
 *
 * ⭐️ここが仕組みの肝。**メンテナンスの実施時間帯は一覧の見出しには出ず、本文にしか
 * 書かれていない**（「システムメンテナンスのため、2026年6月29日（月）24時頃～30日（火）
 * 5時頃まで BOATCAST サイトをご利用いただけません。」）。cron の配置を判断したいなら
 * 本文まで取る必要がある。
 *
 * @author shimomo
 */
final class ArticleParser
{
    /**
     * 本文が取れなければ null。呼び出し側は見出しとリンクだけで通知する。
     *
     * 古い記事は削除されて「不正なURLへのリクエストです。」の案内ページに差し替わる
     * （HTTP 200 で返ってくるのでステータスでは判別できない）。そのページには
     * div.news5_Text が無いので、ここが null を返すことで自然に弾ける。
     */
    public function parse(string $html): ?string
    {
        $xpath = Html::xpath($html);

        $body = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " news5_Text ")]')?->item(0);

        if ($body === null) {
            return null;
        }

        $text = Html::textWithBreaks($body);

        return $text === '' ? null : $text;
    }
}
