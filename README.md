# Laravel Batch Monthly Report

ECサービスを想定した「月次集計 + リマインドメール」バッチ処理のポートフォリオ実装です。  
Laravel 11 の Artisan Command / Scheduler / Queue / Mailable を組み合わせ、実務レベルの設計を意識して構築しました。

---

## 概要・背景

ECサービスでは月末締めの請求処理や未払い顧客へのリマインドが定期的に発生します。  
このプロジェクトでは以下の業務フローをバッチ処理として実装しています：

1. 前月分の注文データを集計し、`monthly_reports` テーブルに保存
2. 前月に未払い注文があるユーザーへリマインドメールを送信
3. 実行履歴を `batch_execution_logs` に記録し、冪等性を担保

---

## 使用技術

| カテゴリ | 技術 |
|---------|------|
| バックエンド | PHP 8.2 / Laravel 11.x |
| データベース | MySQL 8.0 |
| インフラ | Docker / Docker Compose / Nginx |
| バッチ | Laravel Artisan Command / Scheduler |
| 非同期処理 | Laravel Queue（database ドライバ） |
| メール | Laravel Mailable / Mailtrap（ローカル確認） |
| タイムゾーン | Asia/Tokyo（JST）|

---

## 設計上の工夫

### 1. 冪等性の担保

`batch_execution_logs` テーブルを使って二重実行を防止しています。

```
バッチ起動
  ↓
同月の status=success ログが存在する？
  → YES: 警告を表示してスキップ
  → NO: status=running でログを作成して処理開始
         ↓
       処理完了 → status=success に更新
       例外発生 → status=failed に更新 + エラーメッセージを memo に保存
```

`--force` オプションを付けることで成功済みでも強制再実行が可能です。

```bash
php artisan app:monthly-report --force
```

### 2. Service クラスへのロジック分離

コマンドクラス（`MonthlyReportCommand`）は薄いラッパーとして振る舞い、  
ビジネスロジックはすべて `MonthlyReportService` に分離しています。

```
MonthlyReportCommand（薄い）
  ↓ 冪等性チェック・ログ記録
  ↓ サービス呼び出し
MonthlyReportService（厚い）
  ↓ 集計クエリ → monthly_reports に保存（DB::transaction）
  ↓ 未払いユーザー取得 → SendMonthlyReminderJob をディスパッチ
```

### 3. N+1 問題の回避

未払いユーザーへのリマインド処理では `with()` による **Eager Loading** を使用し、  
ループ内で追加クエリが発生しない設計にしています。

```php
// N+1 が発生しない例（MonthlyReportService）
$unpaidUsers = User::whereHas('orders', fn($q) => $q->whereBetween(...)->where('status', 'unpaid'))
    ->with(['orders' => fn($q) => $q->whereBetween(...)->where('status', 'unpaid')])
    ->get();
```

### 4. SerializesModels とキュー越しのデータ受け渡し

`SendMonthlyReminderJob` は `SerializesModels` トレイトを使用しています。このトレイトはジョブをキューに保存する際、Eloquentモデルをプライマリキーのみで直列化します。Worker がジョブを取り出すとき `User::find($id)` で再取得するため、`MonthlyReportService` で設定した Eager Loading の制約（日付・ステータス絞り込み）が消滅します。

このため、Blade テンプレートで `$user->orders` を参照すると全注文が遅延ロードされる問題が発生します。

**対策：** Job の `handle()` 内で改めて対象月・未払いで絞り込み、フィルタ済みの注文コレクションを Mailable に明示的に渡す設計にしています。

```php
// SendMonthlyReminderJob::handle()
$unpaidOrders = $this->user->orders()
    ->whereBetween('created_at', [$start, $end])
    ->where('status', 'unpaid')
    ->get();

Mail::to($this->user->email)
    ->send(new MonthlyReminderMail($this->user, $this->targetMonth, $unpaidOrders));
```

### 5. Queue による非同期メール送信

メール送信は `SendMonthlyReminderJob` としてキューに積み、バッチ本体とは分離しています。  
これにより、ユーザー数が多い場合でもバッチのレスポンスタイムが安定します。

- **リトライ**: 最大3回
- **バックオフ**: 指数バックオフ（60秒 → 120秒 → 240秒）
- **失敗時**: `failed_jobs` テーブルに記録 + ログ出力

### 5. タイムゾーン管理

`config/app.php` で `timezone = Asia/Tokyo` を設定することで、  
`Carbon::now()` が常に JST を返します。  
Scheduler の `->timezone('Asia/Tokyo')` でも明示的に JST を指定し、  
サーバーのシステム時刻に依存しない設計にしています。

### 6. DB::transaction による原子性

集計結果の保存処理はトランザクション内で行い、途中失敗時のデータ不整合を防止しています。  
Queue のディスパッチはトランザクション外で行い、ロールバック時にジョブが残ることを防いでいます。

---

## ディレクトリ構成

```
laravel-batch-monthly-report/
├── docker/
│   ├── php/Dockerfile          PHP 8.2-FPM イメージ定義
│   └── nginx/default.conf      Nginx リバースプロキシ設定
├── src/
│   ├── app/
│   │   ├── Console/Commands/
│   │   │   └── MonthlyReportCommand.php  ← バッチコマンド本体
│   │   ├── Jobs/
│   │   │   └── SendMonthlyReminderJob.php ← メール送信 Queue ジョブ
│   │   ├── Mail/
│   │   │   └── MonthlyReminderMail.php   ← Mailable クラス
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── Order.php
│   │   │   ├── MonthlyReport.php
│   │   │   └── BatchExecutionLog.php
│   │   └── Services/
│   │       └── MonthlyReportService.php  ← 集計ロジック（ビジネス層）
│   ├── database/
│   │   ├── migrations/         テーブル定義
│   │   └── seeders/            テストデータ（ユーザー5件・注文20件）
│   ├── resources/views/emails/
│   │   └── monthly_reminder.blade.php  ← HTML メールテンプレート
│   └── routes/
│       └── console.php         Scheduler 定義（毎月1日 09:00 JST）
├── docker-compose.yml
└── Makefile
```

---

## ローカル起動方法

### 前提条件

- Docker Desktop がインストールされていること
- make コマンドが使えること（Git Bash / WSL2 推奨）

### 初回セットアップ

```bash
# 1. リポジトリをクローン
git clone https://github.com/your-username/laravel-batch-monthly-report.git
cd laravel-batch-monthly-report

# 2. Mailtrap の認証情報を設定（後述）
cp src/.env.example src/.env
# src/.env の MAIL_USERNAME, MAIL_PASSWORD を Mailtrap の値に変更

# 3. セットアップ実行（Docker 起動 → composer install → migrate → seed）
make setup
```

### Mailtrap の設定

1. [Mailtrap](https://mailtrap.io) にアクセスしてアカウント作成
2. `Email Testing > Inboxes` からインボックスを選択
3. `SMTP Settings > Integrations` で **Laravel 9+** を選択
4. 表示された `MAIL_USERNAME`, `MAIL_PASSWORD` を `src/.env` に貼り付け

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
```

---

## 動作確認手順

### バッチの実行

```bash
# 月次レポートバッチを実行
make batch

# または直接コマンドで
docker compose exec app php artisan app:monthly-report
```

実行すると以下の処理が行われます：
1. 前月分の注文を集計 → `monthly_reports` テーブルに保存
2. 未払いユーザー（user2, user4, user5）へのメール送信ジョブをキューに積む
3. `batch_execution_logs` に成功ログを記録

### Queue Worker の起動

```bash
# Queue Worker を起動（別ターミナルで実行）
make worker

# または
docker compose exec app php artisan queue:work --verbose
```

Worker が起動すると `jobs` テーブルに積まれたメール送信ジョブが処理され、  
Mailtrap のインボックスにリマインドメールが届きます。

### 冪等性の確認

```bash
# 2回目の実行 → スキップされることを確認
make batch

# --force で強制再実行
make batch-force
```

### ログの確認

```bash
# バッチ実行ログをDBで確認
docker compose exec app php artisan tinker
>>> App\Models\BatchExecutionLog::all()

# monthly_reports の確認
>>> App\Models\MonthlyReport::all()
```

### スケジューラーの確認

```bash
# 登録されているスケジュールを確認
docker compose exec app php artisan schedule:list

# スケジューラーを手動で一度だけ実行
docker compose exec app php artisan schedule:run
```

---

## テストデータ構成

シーダーが投入するデータ：

| ユーザー | 前月の注文 | 状況 |
|---------|-----------|------|
| 山田 太郎 | paid ×3件 | リマインド対象外 |
| 佐藤 花子 | paid ×2件 + **unpaid ×2件** | **リマインド対象** |
| 鈴木 一郎 | paid ×3件 | リマインド対象外 |
| 田中 美咲 | paid ×2件 + **unpaid ×1件** | **リマインド対象** |
| 高橋 健太 | **unpaid ×2件** | **リマインド対象** |

バッチ実行後、佐藤・田中・高橋の3名にリマインドメールが送信されます。

---

## 主なコマンド一覧

```bash
make setup          # 初回セットアップ
make up             # コンテナ起動
make down           # コンテナ停止
make bash           # appコンテナにシェルで入る
make batch          # バッチ実行
make batch-force    # 強制再実行
make worker         # Queue Worker 起動
make logs           # ログ表示
make ps             # コンテナ状態確認
```
