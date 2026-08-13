<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier;

use JsonException;
use RuntimeException;

/**
 * 通知済みの記事を JSON に持つ。
 *
 * ⭐️**「見た記事」ではなく「通知した記事」だけを記録する。** フィルタは決定的なので、
 * 素通ししたニュースを覚えておく必要がない。結果としてファイルは年に数行しか増えず、
 * git diff で「いつ何を鳴らしたか」がそのまま履歴になる。
 *
 * actions/cache を使わないのは、7 日でも触られなければ evict されるため。
 * メンテナンス記事は数ヶ月に 1 本なので、キャッシュだと必ず消えた後に来る＝同じ記事で再通知する。
 *
 * @author shimomo
 */
final class SeenStore
{
    /** 履歴の上限。超えたら古い順に捨てる。 */
    private const int MAX_ENTRIES = 500;

    /** @var array<string, array{url: string, title: string, published_on: string, notified_at: string}> */
    private array $entries = [];

    private bool $dirty = false;

    private readonly bool $existed;

    public function __construct(private readonly string $path)
    {
        $this->existed = is_file($this->path);

        if (! $this->existed) {
            return;
        }

        $raw = file_get_contents($this->path);

        if ($raw === false) {
            throw new RuntimeException("状態ファイルを読めませんでした: {$this->path}");
        }

        if (trim($raw) === '') {
            return;
        }

        try {
            /** @var array{notified?: list<array{url?: string, title?: string, published_on?: string, notified_at?: string}>} $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("状態ファイルが壊れています: {$this->path}（{$exception->getMessage()}）");
        }

        foreach ($decoded['notified'] ?? [] as $entry) {
            $url = $entry['url'] ?? '';

            if ($url === '') {
                continue;
            }

            $this->entries[$url] = [
                'url' => $url,
                'title' => $entry['title'] ?? '',
                'published_on' => $entry['published_on'] ?? '',
                'notified_at' => $entry['notified_at'] ?? '',
            ];
        }
    }

    /**
     * 初回起動か（＝状態ファイルがまだ無いか）。
     *
     * 呼び出し側はここが true のとき、一覧に残っている過去のメンテナンス記事を
     * まとめて鳴らさないよう bootstrap するかどうかを決める。
     */
    public function isFresh(): bool
    {
        return ! $this->existed;
    }

    public function has(string $url): bool
    {
        return isset($this->entries[$url]);
    }

    public function remember(NewsItem $item, string $notifiedAt): void
    {
        if ($this->has($item->url)) {
            return;
        }

        $this->entries[$item->url] = [
            'url' => $item->url,
            'title' => $item->title,
            'published_on' => $item->publishedOn,
            'notified_at' => $notifiedAt,
        ];

        $this->dirty = true;
    }

    /**
     * 変更が無ければ書かない。ワークフローの `git diff --quiet` がそのまま
     * 「コミットするかどうか」の判定になる。
     */
    public function save(): bool
    {
        if (! $this->dirty && $this->existed) {
            return false;
        }

        if (count($this->entries) > self::MAX_ENTRIES) {
            $this->entries = array_slice($this->entries, -self::MAX_ENTRIES, preserve_keys: true);
        }

        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            throw new RuntimeException("状態ファイルの置き場を作れませんでした: {$directory}");
        }

        $json = json_encode(
            ['version' => 1, 'notified' => array_values($this->entries)],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        if (file_put_contents($this->path, $json . "\n") === false) {
            throw new RuntimeException("状態ファイルを書けませんでした: {$this->path}");
        }

        return true;
    }
}
