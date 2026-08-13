<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier;

use BVP\MaintenanceNotifier\Contracts\HttpClientInterface;
use Closure;
use Throwable;

/**
 * 一覧を取る → メンテ系だけ残す → 未通知だけ本文を足して Discord に投げる → 記録する。
 *
 * ⚠️ 順序は「送ってから記録する」。逆にすると送信失敗が永久に取り返せない
 * （記録済みなので次回も送らない）。この順序なら最悪でも二重通知で済む——
 * 実際、通知は成功したのに state の push が失敗した場合は次の回に同じ記事をもう一度鳴らす。
 * 数ヶ月に 1 本の頻度で、取りこぼしより二重のほうが安い。
 *
 * @author shimomo
 */
final class Watcher
{
    /** 1 回の実行で送る上限。フィルタを緩めすぎた事故で通知が溢れるのを防ぐ。 */
    private const int MAX_NOTIFICATIONS_PER_RUN = 10;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly NewsListParser $listParser,
        private readonly ArticleParser $articleParser,
        private readonly MaintenanceFilter $filter,
        private readonly SeenStore $store,
        private readonly ?DiscordNotifier $notifier,
        private readonly string $newsUrl,
        private readonly Closure $log,
        /** Discord の webhook レート制限（5 req / 2 秒程度）に当てないための送信間隔。 */
        private readonly int $sendIntervalSeconds = 1,
    ) {}

    /**
     * @param bool $bootstrap true なら通知せず「通知済み」として記録だけする（初回導入用）
     *
     * @return int 送った件数（bootstrap 時は記録した件数）
     */
    public function run(bool $bootstrap = false, bool $dryRun = false): int
    {
        $items = $this->listParser->parse($this->http->get($this->newsUrl));

        ($this->log)(sprintf('一覧から %d 件を読みました。', count($items)));

        $matched = array_values(array_filter($items, fn (NewsItem $item): bool => $this->filter->matches($item)));

        ($this->log)(sprintf('うちメンテナンス系は %d 件です。', count($matched)));

        $targets = array_values(array_filter($matched, fn (NewsItem $item): bool => ! $this->store->has($item->url)));

        if ($targets === []) {
            ($this->log)('新しく通知するものはありません。');

            return 0;
        }

        if ($bootstrap) {
            foreach ($targets as $item) {
                ($this->log)(sprintf('[bootstrap] 通知せず記録します: %s', $item->title));
                $this->store->remember($item, $this->now());
            }

            return count($targets);
        }

        $sent = 0;

        foreach ($targets as $item) {
            if ($sent >= self::MAX_NOTIFICATIONS_PER_RUN) {
                ($this->log)(sprintf(
                    '1 回の上限（%d 件）に達したので残り %d 件は次回に回します。',
                    self::MAX_NOTIFICATIONS_PER_RUN,
                    count($targets) - $sent,
                ));

                break;
            }

            $body = $this->fetchBody($item);

            if ($dryRun) {
                ($this->log)(sprintf("[dry-run] %s\n  %s\n  %s", $item->title, $item->url, $body ?? '(本文なし)'));
                $sent++;

                continue;
            }

            if ($this->notifier === null) {
                ($this->log)('DISCORD_WEBHOOK_URL が未設定なので通知しません（記録もしません）。');

                break;
            }

            if ($sent > 0 && $this->sendIntervalSeconds > 0) {
                sleep($this->sendIntervalSeconds);
            }

            $this->notifier->notify($item, $body);
            $this->store->remember($item, $this->now());
            $sent++;

            ($this->log)(sprintf('通知しました: %s', $item->title));
        }

        return $sent;
    }

    /**
     * 本文の取得に失敗しても通知そのものは落とさない。
     * 見出しとリンクだけでも「メンテがある」ことは伝わる。
     */
    private function fetchBody(NewsItem $item): ?string
    {
        if (! $item->hasArticlePage()) {
            return null;
        }

        try {
            return $this->articleParser->parse($this->http->get($item->url));
        } catch (Throwable $exception) {
            ($this->log)(sprintf('本文を取得できませんでした（%s）: %s', $item->url, $exception->getMessage()));

            return null;
        }
    }

    private function now(): string
    {
        return date('c');
    }
}
