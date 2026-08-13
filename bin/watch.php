<?php

declare(strict_types=1);

namespace BVP\MaintenanceNotifier;

/**
 * boatrace.jp のニュース一覧からメンテナンス情報を拾って Discord へ通知する。
 *
 *   php bin/watch.php                 通常実行（未通知のメンテ記事を送って記録する）
 *   php bin/watch.php --dry-run       送らずに、送る内容を標準出力に出す
 *   php bin/watch.php --bootstrap     送らずに「通知済み」として記録する（初回導入用）
 *
 * 環境変数:
 *   DISCORD_WEBHOOK_URL   必須（--dry-run / --bootstrap を除く）
 *   DISCORD_MENTION       任意。'<@&123...>' などを入れるとロールに鳴らせる
 *   NEWS_URL              任意。既定は https://www.boatrace.jp/owpc/pc/site/news/
 *   STATE_FILE            任意。既定は <repo>/state/seen.json
 *
 * @author shimomo
 */

// GitHub Actions のランナーは UTC。通知に出る時刻と、boatrace.jp の掲載日を揃える。
date_default_timezone_set('Asia/Tokyo');

// composer なしで動かせるようにしておく（Actions で毎時 `composer install` を挟まないため）。
// ローカルで phpunit を入れてある場合はそちらの autoload を優先する。
$autoload = __DIR__ . '/../vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'BVP\\MaintenanceNotifier\\';

        if (! str_starts_with($class, $prefix)) {
            return;
        }

        $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($path)) {
            require $path;
        }
    });
}

$arguments = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $arguments, true);
$bootstrap = in_array('--bootstrap', $arguments, true);

$webhookUrl = (string) getenv('DISCORD_WEBHOOK_URL');
$log = static function (string $message): void {
    fwrite(STDOUT, $message . PHP_EOL);
};

if ($webhookUrl === '' && ! $dryRun && ! $bootstrap) {
    fwrite(STDERR, 'DISCORD_WEBHOOK_URL が設定されていません。' . PHP_EOL);

    exit(1);
}

$http = new HttpClient();

try {
    $store = new SeenStore((string) (getenv('STATE_FILE') ?: __DIR__ . '/../state/seen.json'));

    // 初回は一覧に残っている過去のメンテ記事をまとめて鳴らしてしまうので、自動で bootstrap に倒す。
    if ($store->isFresh() && ! $bootstrap && ! $dryRun) {
        $log('状態ファイルが無いので初回とみなし、通知せず記録だけします（次回から通知します）。');
        $bootstrap = true;
    }

    $watcher = new Watcher(
        http: $http,
        listParser: new NewsListParser(),
        articleParser: new ArticleParser(),
        filter: new MaintenanceFilter(),
        store: $store,
        notifier: $webhookUrl === '' ? null : new DiscordNotifier($http, $webhookUrl, (string) getenv('DISCORD_MENTION')),
        newsUrl: (string) (getenv('NEWS_URL') ?: 'https://www.boatrace.jp/owpc/pc/site/news/'),
        log: $log,
    );

    $count = $watcher->run(bootstrap: $bootstrap, dryRun: $dryRun);

    if (! $dryRun) {
        $store->save();
    }

    $log(sprintf('完了しました（%d 件）。', $count));
} catch (\Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);

    exit(1);
}
