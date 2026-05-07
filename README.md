# laravel-video-streaming

Laravel + Inertia + Vue 3 で作った、ローカル動画ライブラリ兼ストリーミングアプリです。
ファイルをブラウザで閲覧し、再生し、視聴履歴やお気に入りを管理し、必要に応じて HLS キャッシュを管理できます。

この README は、ユーザー視点での機能説明と、実装の内部仕様の両方が分かることを目的にしています。

## 概要

- ローカルの動画フォルダをライブラリとしてブラウズできます
- `mp4` はそのまま直接再生します
- `m2ts` / `avi` / `flv` / `vob` はオンデマンドで HLS に変換して再生します
- 視聴履歴、再開位置、お気に入り、視聴済み状態をユーザー単位で保持します
- 管理者はユーザー管理と HLS キャッシュ管理を行えます
- Google ログインにも対応していますが、登録済みメールアドレスのみ利用できます

## 主な機能

### 認証とプロフィール

- 通常のログイン、ログアウト、ユーザー登録、パスワード再設定、メール認証を利用できます
- プロフィール画面で名前、メールアドレス、パスワードの更新とアカウント削除ができます
- Google ログインを利用できますが、Google 側で認証できても、このアプリに登録済みのメールアドレスでなければログインできません

内部仕様:

- 認証は Laravel Breeze の標準構成をベースにしています
- `dashboard` は `auth` かつ `verified` の middleware が付いているため、未認証または未検証のままでは入れません
- Google ログインのコールバックではメールアドレスだけで既存ユーザーを検索し、見つからなければログインさせません

### ダッシュボード

- ログイン直後の入口です
- `Video Library`、`Favorites`、`Viewing History` のカードから主要機能へ移動できます
- 管理者には `User Management` と `HLS Cache` のカードも表示されます
- デスクトップでは横並び、モバイルではハンバーガーメニューに切り替わります

内部仕様:

- 表示内容はユーザーの `role` で出し分けています
- 管理者用ナビはデスクトップとモバイルの両方にあります

### Video Library

- 動画フォルダをツリー表示でたどれます
- パンくず、親フォルダへの戻りリンクがあります
- ファイルサイズ、視聴済み、公開キャッシュ済み、お気に入りなどの状態が見えます
- 管理者はライブラリ画面から HLS キャッシュを削除できます
- 一般ユーザーはキャッシュの状態だけ確認できます

内部仕様:

- ライブラリは `VIDEO_ROOT` 配下を走査し、ユーザーの許可パスに一致するものだけ表示します
- 対象拡張子は `mp4`, `m2ts`, `avi`, `flv`, `vob` です
- フォルダも `videos` マスタに登録して、階層構造を DB に持ちます
- `Video` の `hash` は `md5(path)` で計算し、HLS キャッシュのディレクトリ名に使います
- `Favorite` と `VideoView` の状態を、画面表示用のタグとして合成しています

### 再生

- `mp4` は `Direct Play` としてそのまま再生します
- `m2ts` / `avi` / `flv` / `vob` は HLS プレイヤーで再生します
- HLS 再生画面では、画質切り替えと音声トラック切り替えができます
- 視聴位置は定期保存され、途中再生から再開できます
- HLS が初回生成中にネットワークエラーが出た場合は、再読み込みカウントダウンを表示します

内部仕様:

- `mp4` は `/stream/{path}` で `VideoStream` により Range 対応配信されます
- HLS 対象ファイルは `/watch/{path}` に入ると `ensureHls()` が実行され、必要なら `ffmpeg` を起動します
- HLS 配信は `/hls/{hash}/{file}` で行います
- 視聴位置は `video_views.last_position` に保存されます
- 保存タイミングは 10 秒ごと、ポーズ時、アンマウント時です

### Favorites

- フォルダとファイルをお気に入り登録できます
- お気に入り一覧からすぐにライブラリまたは再生画面へ移動できます
- お気に入り一覧では、キャッシュ済みか、視聴済みか、再開位置があるかを確認できます

内部仕様:

- `favorites` テーブルでユーザー単位に保存します
- 一覧では `Favorite` に加えて `VideoView` を参照し、再開位置も表示します
- HLS キャッシュ済みかどうかはライブラリと同じ判定を使います

### Viewing History

- 最近視聴した動画が一覧表示されます
- 途中再生の位置と最終視聴時刻が分かります
- `Resume` から再開できます

内部仕様:

- `video_views` を `updated_at desc` で並べています
- 現在のユーザーがまだアクセスできるパスだけに絞り込みます
- 取得時に保存済みの再生位置をそのまま再開位置として使います

### User Management

- 管理者はユーザーの一覧、作成、編集、削除ができます
- ロールは `admin` / `user` の 2 種類です
- ユーザーごとにアクセス可能な動画パスを設定できます

内部仕様:

- 管理者だけがアクセスできます
- ユーザー削除では自分自身は削除できません
- `allowed_paths` は親パスを選ぶと子パスが冗長になるように整理されます
- root (`''`) を許可すると全体アクセスになります

### HLS Cache Management

- 管理者は HLS キャッシュの状態、サイズ、進捗、ディスク残量を確認できます
- キャッシュ単位で削除できます
- 複数選択削除と全削除もできます

内部仕様:

- 管理画面の状態表示は `ffmpeg.pid`、`index.m3u8`、`transcoding.lock` から推定します
- `ffmpeg.pid` が生きていれば `Transcoding`。**進捗率（%）とプログレスバー**が表示されます
- `index.m3u8` があり、`transcoding.lock` が無ければ `Completed`
- それ以外は `Failed / Incomplete`
- 変換開始時に `ffprobe` で総時間を取得し `total_duration.txt` に保存、`ffmpeg.log` の最新出力と比較して進捗を計算します
- キャッシュサイズ取得は非同期で、同時実行を `Cache::lock` で抑制します
- 削除時は `ffmpeg.pid` などのプロセスがあれば安全に止めてからディレクトリを消します

### 保守コマンド

- `app:refresh-video-paths` コマンドがあります
- 物理的な動画ファイルやデータベースレコード、およびキャッシュの不整合を解消できます
- `--dry-run` で削除せずに確認だけできます

内部仕様:

- `videos` マスタと `user_allowed_paths` を走査して、実体のないレコードを見つけます
- VOB の冗長ファイル（`VTS_01_1.VOB` 以外）を整理対象として扱います
- **孤立キャッシュの掃除**: データベースにレコードが存在しない `hash` 名のキャッシュディレクトリを物理ディレクトリのスキャンにより自動検知し、削除します。これにより `Unknown (Source path not in database)` な項目を完全に一掃できます。


## 対応動画形式と再生方式

| 形式 | 再生方式 | キャッシュ | 補足 |
| --- | --- | --- | --- |
| `mp4` | 直接再生 | なし | ブラウザの `<video>` と Range 配信で再生します |
| `m2ts` | HLS 変換 | あり | 初回再生時に `ffmpeg` で HLS を生成します |
| `avi` | HLS 変換 | あり | 初回再生時に HLS を生成します |
| `flv` | HLS 変換 | あり | 初回再生時に HLS を生成します |
| `vob` | HLS 変換 | あり | DVD 構成を意識した特別処理があります |

### VOB の内部仕様

VOB はこのアプリで最もチューニングを重ねた形式です。

- 再生入口は `VTS_01_1.VOB` に統一します
- `VTS_01_2.VOB` など他の VOB を直接開いても、同じタイトルの先頭ファイルにリダイレクトします
- 同一ディレクトリの VOB は `VTS_xx_*.VOB` を拾って `concat:` で連結します
- `VTS_01_0.VOB` は対象外です
- 連結順は自然順です。`VTS_01_10.VOB` が `VTS_01_2.VOB` の前に来ないようにしています
- インターレース解除に `yadif` を使います
- タイムスタンプ安定化のために `-fflags +genpts+igndts -avoid_negative_ts make_zero` を付けています
- 音声トラックは最大 2 本まで HLS に載せます

## HLS キャッシュの内部仕様

HLS キャッシュは `storage/hls/{md5(path)}` に保存されます。

主なファイルは次の通りです。

- `index.m3u8`: master playlist
- `p0.m3u8`, `p1.m3u8`: 画質別の variant playlist
- `s0_0.ts` など: セグメント
- `ffmpeg.pid`: 変換中プロセスの PID
- `transcoding.lock`: 変換中または中断中であることを示すライフサイクルファイル
- `ffmpeg.log`: 変換ログ

判定の考え方:

- `index.m3u8` があり、`transcoding.lock` が無い場合は完了済み扱いです
- `transcoding.lock` がある場合は、途中で止まったキャッシュとして再変換対象にできます
- `ffmpeg.pid` が生きていれば変換中です
- 以前の古いキャッシュも、`index.m3u8` があれば完了扱いにして後方互換を保っています

この仕組みは、過去に発生した次のような不具合を解消するために入れています。

- 変換完了と未完了の区別が曖昧だった
- 途中失敗した HLS キャッシュが完了扱いになっていた
- VOB や長尺動画の変換状態が管理画面で追いにくかった

## インストール

### 前提

- Docker
- Docker Compose
- ローカルの動画フォルダ
- `localhost.app.sample.jp` と `localhost.node.sample.jp` にアクセスできるようにするための hosts 設定

### 1. リポジトリをクローンする

```bash
git clone <this-repository-url>
cd laravel-video-streaming
```

### 2. `docker-compose.yaml.sample` をコピーして本番用ファイルを作る

```bash
cp docker-compose.yaml.sample docker-compose.yaml
```

必要に応じて、動画のマウント先を調整してください。

- そのまま自分の `Movies` フォルダを使うなら、サンプルの `~/Movies:/videos:ro` を使えます
- リポジトリ配下に動画を置きたいなら、`./videos:/videos:ro` のコメントを外して使えます

**セキュリティノート**: 動画ファイルは `:ro` (Read-Only) フラグ付きでマウントされます。これにより、アプリケーションが誤って元の動画ファイルを編集または削除することを防ぎ、データを安全に保護します。

### 3. `app/.env` を作る

```bash
cp app/.env.example app/.env
```

少なくとも次を確認してください。

- `APP_URL=http://localhost.app.sample.jp`
- `DB_HOST=db`
- `DB_DATABASE=db`
- `DB_USERNAME=user`
- `DB_PASSWORD=password`
- Google ログインを使うなら `GOOGLE_KEY` / `GOOGLE_SECRET` / `GOOGLE_REDIRECT_URI`

`GOOGLE_REDIRECT_URI` の例:

```text
http://localhost.app.sample.jp/auth/google/callback
```

### 4. hosts を追加する

```text
127.0.0.1 localhost.app.sample.jp
127.0.0.1 localhost.node.sample.jp
```

### 5. Docker コンテナを起動する

```bash
docker compose up -d --build
```

この構成では、次のサービスが立ち上がります。

- `app`: Laravel / PHP-FPM
- `scheduler`: `php artisan schedule:work`
- `node`: Vite 開発サーバー
- `nginx-app`: アプリの nginx
- `db`: MySQL
- `nginx-proxy`: ホスト名振り分け
- `redis`: セッション・キャッシュ用

### 6. Laravel の初期化を行う

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

`DatabaseSeeder` から `UserSeeder` が呼ばれ、以下の初期アカウントが作成されます。

- email: `test@test.com`
- password: `password123`
- role: `admin`

### 7. ブラウザで開く

```text
http://localhost.app.sample.jp
```

ログイン画面から以下のアカウントで入れます。

- email: `test@test.com`
- password: `password123`

## 使い方

1. ログインする
2. `Dashboard` から `Video Library` を開く
3. フォルダを辿って動画ファイルを選ぶ
4. `mp4` はそのまま再生される
5. `avi` / `m2ts` / `flv` / `vob` は初回に HLS 変換が走る
6. 必要に応じて、お気に入り登録、視聴済み切り替え、再開再生を使う
7. 管理者は `Users` と `HLS Cache` を使って権限やキャッシュを管理する

## データモデルの考え方

- `videos`
    - ライブラリ上のファイルやフォルダを表すマスタ
    - `path`, `hash`, `type` を持つ
- `video_views`
    - ユーザーごとの視聴履歴と再生位置
- `favorites`
    - ユーザーごとのお気に入り
- `user_allowed_paths`
    - ユーザーごとのアクセス許可パス

## テスト

アプリ側では feature test を中心にカバーしています。

```bash
cd app
php artisan test
```

主に確認している内容:

- ログイン / ログアウト
- mp4 の直接再生
- HLS 変換のコマンド生成
- 旧キャッシュの後方互換
- lock file 付きの壊れたキャッシュの再変換
- VOB の自然順連結
- 視聴履歴、進捗保存、お気に入り
- 管理者の HLS キャッシュ削除とユーザー管理

## 補足

- 画面の主導線は URL の直打ちではなく、ライブラリ内のリンクやボタン操作です
- HLS の初回変換は時間がかかることがあります
- `HLS Cache Management` で `Failed / Incomplete` と出ていても、ロックファイルが残っているだけの一時状態であることがあります
- `transcoding.lock` と `ffmpeg.pid` は、変換中と完了済みを見分けるための内部状態です

## ブラウザ確認の基準

このドキュメントを基準にブラウザ確認する場合は、まず次の順で確認してください。

1. ログインできること
2. Dashboard から `Video Library` / `Favorites` / `Viewing History` / 管理者向けメニューへ遷移できること
3. `mp4` が直接再生されること
4. `m2ts` / `avi` / `flv` / `vob` が HLS 変換されて再生されること
5. 視聴履歴が保存され、再開位置が復元できること
6. お気に入りが保存され、一覧から再遷移できること
7. 管理者が HLS キャッシュとユーザーを管理できること

### 合格条件

各機能の確認では、次の状態を満たしていれば合格とします。

- ログイン後に Dashboard が表示される
- ライブラリで許可されたパスだけが見える
- `mp4` の画面には `Direct Play` が出る
- HLS 対象ファイルの画面には `Preparing` または `Cached` が出る
- 初回変換後は HLS キャッシュ管理画面で `Completed` と判定される
- 変換途中は `Transcoding...` と判定される
- 途中失敗またはロック残存は `Failed / Incomplete` と判定される
- VOB は `VTS_01_1.VOB` を起点として再生される
- `VTS_01_2.VOB` などを直接開いても先頭ファイルへ寄る
- 視聴履歴に再生位置が残る
- お気に入り一覧に登録内容が出る
- 管理者はキャッシュ削除とユーザー管理を行える

### 非合格の例

次のような状態は、このアプリの仕様に対して不合格です。

- `mp4` が HLS として扱われる
- `avi` / `m2ts` / `flv` / `vob` が直接再生される
- キャッシュ済みなのに HLS 管理画面で `Failed / Incomplete` になる
- `transcoding.lock` が残っているのに完了扱いになる
- VOB の連結順が辞書順になっている
- 視聴履歴やお気に入りが別ユーザーに見える
- 許可していないパスが表示される

### 重点確認項目

リファクタリング時は、とくに次を重点的に見てください。

- HLS 変換の開始条件
- HLS 変換完了条件
- ロックファイルの扱い
- 旧キャッシュとの後方互換
- VOB の自然順連結
- 途中再生の保存と復元
- ユーザーごとのアクセス制御
- 管理者権限のみに限定された削除操作
