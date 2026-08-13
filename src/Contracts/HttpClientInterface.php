<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier\Contracts;

use RuntimeException;

/**
 * Watcher と DiscordNotifier が通信手段を差し替えられるようにするためだけの口。
 * 本番の実装は HttpClient 1 つで、テストでは録音用の差し替えを入れる。
 *
 * @author shimomo
 */
interface HttpClientInterface
{
    /**
     * @throws RuntimeException
     */
    public function get(string $url): string;

    /**
     * @param array<string, mixed> $payload
     *
     * @throws RuntimeException
     */
    public function postJson(string $url, array $payload): string;
}
