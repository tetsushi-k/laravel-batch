#!/usr/bin/env bash
# =============================================================
# Cloud Agent 用セットアップスクリプト（Docker 不使用 / SQLite）
#
# 一時 VM 上で Laravel Batch を直接 PHP で動かすためのブートストラップ。
# ベースイメージ（スナップショット）に PHP 8.3 + Composer が入っている前提で、
# リポジトリ依存のセットアップ（composer install / .env / migrate / seed）を行う。
#
# 冪等性:
#   何度実行しても同じ状態に収束する。migrate:fresh --seed でテーブルを作り直す。
# =============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$SCRIPT_DIR/../src"
cd "$APP_DIR"

echo "=== [1/5] composer install ==="
composer install --no-interaction --prefer-dist --no-progress

echo "=== [2/5] .env 生成（SQLite / MAIL=log / QUEUE=sync）==="
if [ ! -f .env ]; then
  cat > .env <<'ENV'
APP_NAME="Laravel Batch"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=Asia/Tokyo
APP_URL=http://localhost
APP_LOCALE=ja
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=ja_JP

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# データベース: ホストを汚さないため SQLite を使用（DB_DATABASE 未設定で database/database.sqlite）
DB_CONNECTION=sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
CACHE_STORE=database

# キュー: queue コンテナ不要のため同期実行
QUEUE_CONNECTION=sync

# メール: SMTP 不要のため log ドライバへ出力
MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@example.com"
MAIL_FROM_NAME="${APP_NAME}"

# Slack: 未設定なら通知をスキップして正常終了
SLACK_WEBHOOK_URL=
ENV
fi

echo "=== [3/5] アプリケーションキー生成 ==="
if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --ansi --force
fi

echo "=== [4/5] SQLite データベースファイル準備 ==="
mkdir -p database
touch database/database.sqlite

echo "=== [5/5] migrate + seed（冪等: fresh で作り直し）==="
php artisan migrate:fresh --seed --force

echo "=== セットアップ完了 ==="
echo "動作確認: (cd src && php artisan app:daily-sales-alert --dry-run)"
