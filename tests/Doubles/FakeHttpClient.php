<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier\Tests\Doubles;

use BVP\MaintenanceNotifier\Contracts\HttpClientInterface;
use RuntimeException;

/**
 * URL → レスポンス本文の対応表を持つだけの差し替え。POST は投げた内容を録音する。
 *
 * @author shimomo
 */
final class FakeHttpClient implements HttpClientInterface
{
    /** @var list<string> */
    public array $requested = [];

    /** @var list<array{url: string, payload: array<string, mixed>}> */
    public array $posted = [];

    /**
     * @param array<string, string> $responses URL => 本文
     * @param list<string>          $failing   get() で例外を投げる URL
     */
    public function __construct(
        private readonly array $responses = [],
        private readonly array $failing = [],
    ) {}

    public function get(string $url): string
    {
        $this->requested[] = $url;

        if (in_array($url, $this->failing, true)) {
            throw new RuntimeException("取得に失敗しました: {$url}");
        }

        return $this->responses[$url] ?? throw new RuntimeException("用意されていない URL です: {$url}");
    }

    public function postJson(string $url, array $payload): string
    {
        $this->posted[] = ['url' => $url, 'payload' => $payload];

        return '';
    }
}
