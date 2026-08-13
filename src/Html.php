<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier;

use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * DOMDocument まわりの定型処理。
 *
 * @author shimomo
 */
final class Html
{
    /**
     * UTF-8 の HTML 文字列から XPath を作る。
     *
     * ⚠️ loadHTML() は meta charset を見てくれることになっているが、boatrace.jp の
     * ページは meta より前に長い IE 用コメントが入る日があり、その場合 libxml が
     * charset を読み落として日本語が壊れる。数値参照へ逃がしてから食わせれば
     * 入力が ASCII だけになるので、charset の検出結果に左右されなくなる。
     */
    public static function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();

        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8'),
                LIBXML_NONET,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }

    /**
     * ノードのテキストを 1 行に潰して返す。
     *
     * 全角スペース（U+3000）も空白として畳む。boatrace.jp の見出しは
     * 「第72回ボートレースメモリアル　ドリーム戦出場選手発表」のように
     * 全角スペースで区切ってあることがある。
     */
    public static function text(?DOMNode $node): string
    {
        if ($node === null) {
            return '';
        }

        return self::collapse($node->textContent);
    }

    /**
     * <br> を改行として残しつつテキストを取り出す。
     *
     * 記事本文は「メンテナンス時間帯」と「お詫び」が <br> 区切りで 1 つの <p> に
     * 入っているので、ここを潰すと読みづらくなる。
     */
    public static function textWithBreaks(?DOMNode $node): string
    {
        if ($node === null) {
            return '';
        }

        $document = $node->ownerDocument;

        if ($document === null) {
            return self::text($node);
        }

        $html = (string) $document->saveHTML($node);
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</(p|div|li|tr|h\d)>#i', "\n", $html) ?? $html;

        $lines = array_map(
            static fn (string $line): string => self::collapse($line),
            explode("\n", html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        );

        return trim(implode("\n", array_filter($lines, static fn (string $line): bool => $line !== '')));
    }

    private static function collapse(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{3000}]+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
