<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier;

use BVP\MaintenanceNotifier\Contracts\HttpClientInterface;

/**
 * Discord の Incoming Webhook に embed を 1 通投げる。
 *
 * @author shimomo
 */
final class DiscordNotifier
{
    /** Discord 側の上限。超えると 400 で弾かれるので手前で切る。 */
    private const int TITLE_LIMIT = 256;

    private const int DESCRIPTION_LIMIT = 4096;

    /** 橙。メンテナンス＝「壊れてはいないが手当てが要る」の色。 */
    private const int COLOR = 0xE8_75_00;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $webhookUrl,
        private readonly string $mention = '',
    ) {}

    /**
     * @param string|null $body 記事本文。取れなければ null（見出しとリンクだけで送る）
     */
    public function notify(NewsItem $item, ?string $body): void
    {
        $fields = [];

        if ($item->publishedOn !== '') {
            $fields[] = ['name' => '掲載日', 'value' => $item->publishedOn, 'inline' => true];
        }

        if ($item->labels !== []) {
            $fields[] = ['name' => 'カテゴリ', 'value' => implode(' / ', $item->labels), 'inline' => true];
        }

        $embed = [
            'title' => $this->truncate($item->title, self::TITLE_LIMIT),
            'url' => $item->url,
            'color' => self::COLOR,
            'fields' => $fields,
            'footer' => ['text' => 'boatrace.jp ニュース'],
        ];

        if ($body !== null && $body !== '') {
            $embed['description'] = $this->truncate($body, self::DESCRIPTION_LIMIT);
        }

        $payload = ['embeds' => [$embed]];

        // content 側にしか入れないのは、embed 内のメンションは通知として鳴らないため。
        if ($this->mention !== '') {
            $payload['content'] = $this->mention;
        }

        $this->http->postJson($this->webhookUrl, $payload);
    }

    private function truncate(string $text, int $limit): string
    {
        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit - 1, 'UTF-8') . '…';
    }
}
