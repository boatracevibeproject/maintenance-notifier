# Maintenance Notifier

boatrace.jp の[ニュース一覧](https://www.boatrace.jp/owpc/pc/site/news/)を 1 時間おきに見て、**メンテナンス系の記事が新しく出たら Discord に流す**。GitHub Actions で完結し、サーバもデータベースも要らない。

## なぜ要るか

boatrace.jp のメンテナンスは**数ヶ月おきの計画メンテナンス**と**緊急メンテナンス**の 2 種類で、毎日走る定期窓のような常設のものは無い。このうち、

- **計画メンテナンスは事前に告知が出る。** 拾えれば `api` の日次バッチ（[scheduling-operations.md](https://github.com/boatracevibeproject/api/blob/main/docs/scheduling-operations.md)）をその日だけずらす判断ができる
- **緊急メンテナンスは事前に知りようがない。** 知ったところで打つ手も無いので、これは拾えなくてよい

つまり狙いは**計画メンテナンスの告知を見落とさないこと**の一点。⭐️**数ヶ月に一度しか出ないからこそ人間が見張るのに向いていない**——毎日ニュース一覧を開き続けられる人はいないし、忘れた頃に出る。

## 何を拾うか

一覧の見出しに以下の語が含まれる記事。**boatrace.jp のニュースにはメンテナンス専用のカテゴリが無く**（`label1` に付くのは「ニュース」「テレボート」等だけ）、見出しの語で判定するしかない。

> メンテナンス / メンテ / 保守 / 停止 / 休止 / 中断 / 障害 / システムエラー / 復旧 / ご利用いただけません ほか

「障害者」「レース中断」のような誤爆は除外リストで落とす。語の増減は [MaintenanceFilter](src/MaintenanceFilter.php) の 2 つの定数だけで済む。

実際に出た見出しの例:

- 【BOATCAST】システムメンテナンスに伴うサービス停止のお知らせ（2026/06/29）
- システムメンテナンスの実施について（2026/05/11）
- 三井住友銀行メンテナンスについて（2026/07/19）

### ⭐️本文まで取りに行く

**実施時間帯は見出しには出ず、本文にしか書かれていない。**

> システムメンテナンスのため、2026年6月29日（月）24時頃～30日（火）5時頃まで BOATCAST サイトをご利用いただけません。

cron の配置を判断したいのはまさにこの時間帯なので、boatrace.jp 内の記事は本文（`div.news5_Text`）まで取って Discord の embed に載せる。外部リンク（boatrace-pr.jp 等）は構造が読めないので見出しとリンクだけにする。

## 重複排除

通知した記事を [state/seen.json](state/seen.json) に記録し、ワークフローがそれを push で戻す。

**「見た記事」ではなく「通知した記事」だけを持つ。** フィルタは決定的なので素通りしたニュースを覚える必要がなく、結果としてファイルは年に数行しか増えない。git のログがそのまま「いつ何を鳴らしたか」の履歴になる。

`actions/cache` を使わないのは、7 日触られないと evict されるため。**メンテ記事は数ヶ月に 1 本なので、キャッシュだと必ず消えた後に来る**——同じ記事で再通知することになる。

⚠️ 順序は**送ってから記録する**。逆にすると送信失敗が永久に取り返せない。この順序なら最悪でも二重通知で済む（通知は成功したが push が失敗した場合、次の回に同じ記事をもう一度鳴らす）。

## 導入

1. このリポジトリを GitHub に置く
2. Settings → Secrets and variables → Actions で **`DISCORD_WEBHOOK_URL`** を Secret に登録（Discord のチャンネル設定 → 連携サービス → ウェブフック）
3. 任意で **`DISCORD_MENTION`** を Variable に登録（`<@&123456789>` のようなロール ID を入れると鳴る）
4. Actions タブから `Watch` を一度 `dry_run` で手動実行して、拾い方を確認する

以降は毎時 0 分（UTC）に自動で回る。

## 手元で動かす

```bash
php bin/watch.php --dry-run     # 送らずに、送る内容を標準出力に出す
php bin/watch.php --bootstrap   # 送らずに「通知済み」として記録する
php bin/watch.php               # 通常実行（DISCORD_WEBHOOK_URL が要る）
```

| 環境変数 | 既定 | 用途 |
|---|---|---|
| `DISCORD_WEBHOOK_URL` | （必須） | Incoming Webhook。`--dry-run` / `--bootstrap` では不要 |
| `DISCORD_MENTION` | 空 | `content` に入れる文字列。ロールに鳴らしたいとき |
| `NEWS_URL` | `https://www.boatrace.jp/owpc/pc/site/news/` | 過去月の一覧（`.../news/pickup/2026/06/`）を指すと検証しやすい |
| `STATE_FILE` | `state/seen.json` | 記録の置き場 |

**実行時の依存はゼロ。** `bin/watch.php` が自前で autoload するので、Actions 側で `composer install` を挟まない。`composer install` が要るのはテストを走らせるときだけ。

```bash
composer install
composer test
```

## 構造が変わったら

一覧の `ul.news4_newsList` が見つからないと [NewsListParser](src/NewsListParser.php) が例外を投げ、ワークフローが失敗して Discord に「⚠️ 取得に失敗しました」が流れる。**拾えなくなったことに気づけない状態を作らないため**に、成功時の通知だけでなく失敗も鳴らしている。

テストの `tests/Fixtures/news-list.html` と `article.html` は boatrace.jp から実際に取得した HTML をそのまま置いてあるので、構造が変わったときはこれを取り直して差分を見る。

## 制約

- **緊急メンテナンスには間に合わない。** 告知が出るとしても発生と同時なので、事前の手当てには使えない（そもそも打つ手が無い）
- **カテゴリではなく見出しの語で判定している。** 「重要なお知らせ」のような語を含まない見出しで告知されたら取りこぼす
- 一覧は 1 ページ 20 件。1 時間おきに見るので溢れることはないが、`Watch` を長期間止めていた場合は取りこぼす
- 古い記事は削除されて「不正なURLへのリクエストです。」の案内ページに差し替わる（**HTTP 200 で返る**）。本文が取れないだけで通知自体は出る
