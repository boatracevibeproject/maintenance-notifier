<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier;

use BVP\MaintenanceNotifier\Contracts\HttpClientInterface;
use CurlHandle;
use RuntimeException;

/**
 * GET / POST を投げるだけの薄い curl ラッパ。
 *
 * 外部パッケージを入れないのは、GitHub Actions 側で `composer install` を挟まずに
 * `php bin/watch.php` だけで動かせるようにするため（この仕組みは 1 時間おきに回るので、
 * 依存の解決に毎回数十秒使いたくない）。
 *
 * @author shimomo
 */
final class HttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly string $userAgent = 'bvp-maintenance-notifier/1.0 (+https://github.com/boatracevibeproject)',
        private readonly int $timeoutSeconds = 20,
        private readonly int $retries = 2,
        private readonly int $retryWaitSeconds = 3,
    ) {}

    /**
     * @throws RuntimeException 全リトライを使い切っても 2xx が返らなかったとき
     */
    public function get(string $url): string
    {
        return $this->send($url, static function (CurlHandle $handle): void {
            curl_setopt($handle, CURLOPT_HTTPGET, true);
        });
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws RuntimeException
     */
    public function postJson(string $url, array $payload): string
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $this->send($url, static function (CurlHandle $handle) use ($body): void {
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
            curl_setopt($handle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        });
    }

    private function send(string $url, callable $configure): string
    {
        $lastError = '';

        for ($attempt = 0; $attempt <= $this->retries; $attempt++) {
            if ($attempt > 0) {
                sleep($this->retryWaitSeconds);
            }

            $handle = curl_init($url);

            if ($handle === false) {
                throw new RuntimeException("curl を初期化できませんでした: {$url}");
            }

            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($handle, CURLOPT_MAXREDIRS, 5);
            curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($handle, CURLOPT_TIMEOUT, $this->timeoutSeconds);
            curl_setopt($handle, CURLOPT_USERAGENT, $this->userAgent);
            curl_setopt($handle, CURLOPT_ENCODING, '');

            $configure($handle);

            $body = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error = curl_error($handle);

            curl_close($handle);

            if ($body === false) {
                $lastError = "通信に失敗しました: {$error}";

                continue;
            }

            if ($status < 200 || $status >= 300) {
                $lastError = "HTTP {$status} が返りました。";

                continue;
            }

            return (string) $body;
        }

        throw new RuntimeException("{$url} の取得に失敗しました（{$lastError}）");
    }
}
