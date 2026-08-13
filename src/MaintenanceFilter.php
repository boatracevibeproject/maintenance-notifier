<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier;

/**
 * 見出しからメンテナンス系の記事を選り分ける。
 *
 * ⚠️ boatrace.jp のニュースには**メンテナンス専用のカテゴリが無い**（label1 に付くのは
 * 「ニュース」「テレボート」等だけ）。したがって見出しの語で判定するしかない。
 * 実績は数ヶ月に 1 本程度で、
 *
 *   - 【BOATCAST】システムメンテナンスに伴うサービス停止のお知らせ（2026/06/29）
 *   - システムメンテナンスの実施について（2026/05/11）
 *   - 三井住友銀行メンテナンスについて（2026/07/19）
 *
 * のような書かれ方をする。
 *
 * ⚠️ 狙いは**事前告知の出る計画メンテナンス**。緊急メンテナンスは告知が出るとしても発生と
 * 同時なので、この仕組みで先回りはできない（できたとしても打つ手が無い）。
 *
 * ⚠️ 頻度が低いぶん、**取りこぼしたときに「鳴らなかったこと」で気づけない**。だから語は
 * 広めに取り、誤爆のほうを DENIED で削る向きに倒してある。
 *
 * @author shimomo
 */
final class MaintenanceFilter
{
    /**
     * これが見出しに含まれていたら拾う。
     *
     * 「停止」「障害」のような 2 文字語も入れてあるのは、メンテナンス以外の言い回し
     * （「サービス停止」「システム障害」）を取りこぼさないため。誤爆は DENIED 側で削る。
     *
     * @var list<string>
     */
    private const array KEYWORDS = [
        'メンテナンス',
        'メンテ',
        '保守',
        '停止',
        '休止',
        '中断',
        '障害',
        'システムエラー',
        '復旧',
        'ご利用いただけません',
        'ご利用できません',
        '利用いただけない',
        'つながりにくい',
        '繋がりにくい',
    ];

    /**
     * KEYWORDS に当たっても、これが含まれていたら捨てる。
     *
     * 「障害」は「障害者割引」「障がい者スポーツ支援」のような記事で誤爆する。
     * 「中断」は荒天によるレース中断でも使われるが、そちらは開催情報であって
     * サイトの可用性の話ではないので落とす。
     *
     * @var list<string>
     */
    private const array DENIED = [
        '障害者',
        '障がい者',
        'レース中断',
        '発売中断',
    ];

    public function matches(NewsItem $item): bool
    {
        $title = $this->normalize($item->title);

        foreach (self::DENIED as $denied) {
            if (str_contains($title, $this->normalize($denied))) {
                return false;
            }
        }

        foreach (self::KEYWORDS as $keyword) {
            if (str_contains($title, $this->normalize($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * 半角カナ・全角英数のゆれを吸収する。見出しは基本 UTF-8 の全角だが、
     * 「ﾒﾝﾃﾅﾝｽ」のような表記が来ても落とさないようにしておく。
     */
    private function normalize(string $text): string
    {
        return mb_convert_kana($text, 'asKV', 'UTF-8');
    }
}
