# =============================================================
# Makefile - Laravel Batch
# Usage: make <target>
# =============================================================

.PHONY: help setup up down restart bash migrate seed batch batch-force daily daily-force daily-date daily-date-force worker logs ps test

help:
	@echo ""
	@echo "Laravel Batch - Available commands"
	@echo "=================================================="
	@echo "  make setup        First-time setup (install, key:generate, migrate, seed)"
	@echo "  make up           Start containers"
	@echo "  make down         Stop containers"
	@echo "  make restart      Restart containers"
	@echo "  make bash         Open shell in app container"
	@echo "  make migrate      Run migrations"
	@echo "  make seed         Seed test data"
	@echo "  make batch        Run monthly report batch"
	@echo "  make batch-force  Force re-run monthly batch (ignore idempotency check)"
	@echo "  make daily              Run daily sales alert batch (yesterday)"
	@echo "  make daily-force        Force re-run daily batch (ignore idempotency check)"
	@echo "  make daily-date DATE=YYYY-MM-DD       Run daily batch for specific date"
	@echo "  make daily-date-force DATE=YYYY-MM-DD Force re-run for specific date"
	@echo "  make worker             Start queue worker in foreground (debug; queue container is already running)"
	@echo "  make logs         Tail container logs"
	@echo "  make ps           Show container status"
	@echo "  make test         Run PHPUnit Feature tests"
	@echo ""

# First-time setup: composer install -> copy .env -> key:generate -> migrate -> seed
setup: up
	@echo "=== composer install ==="
	docker compose exec app composer install
	@echo "=== copy .env ==="
	docker compose exec app cp -n .env.example .env || true
	@echo "=== generate application key ==="
	docker compose exec app php artisan key:generate
	@echo "=== run migrations ==="
	docker compose exec app php artisan migrate --force
	@echo "=== seed test data ==="
	docker compose exec app php artisan db:seed
	@echo ""
	@echo "=== setup complete ==="
	@echo "Run batch: make batch"

up:
	docker compose up -d --build
	@echo "Containers started."

down:
	docker compose down

restart: down up

bash:
	docker compose exec app bash

migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

batch:
	@echo "=== Running monthly report batch ==="
	docker compose exec app php artisan app:monthly-report
	@echo ""
	@echo "Mails are processed by the 'queue' container automatically."
	@echo "Check logs: docker compose logs -f queue"

batch-force:
	@echo "=== Force running monthly report batch ==="
	docker compose exec app php artisan app:monthly-report --force

daily:
	@echo "=== Running daily sales alert batch ==="
	docker compose exec app php artisan app:daily-sales-alert
	@echo ""
	@echo "Check Slack or set SLACK_WEBHOOK_URL in .env"

daily-force:
	@echo "=== Force running daily sales alert batch ==="
	docker compose exec app php artisan app:daily-sales-alert --force

# 任意の日付を指定して実行: make daily-date DATE=2026-05-10
daily-date:
	@echo "=== Running daily sales alert batch for $(DATE) ==="
	docker compose exec app php artisan app:daily-sales-alert --date=$(DATE)

# 任意の日付を強制再実行: make daily-date-force DATE=2026-05-10
daily-date-force:
	@echo "=== Force running daily sales alert batch for $(DATE) ==="
	docker compose exec app php artisan app:daily-sales-alert --date=$(DATE) --force

worker:
	@echo "=== Starting queue worker in foreground (Ctrl+C to stop) ==="
	@echo "Note: 'queue' container is already running. This is for debugging only."
	docker compose exec app php artisan queue:work --verbose

logs:
	docker compose logs -f

ps:
	docker compose compose ps

test:
	@echo "=== Running PHPUnit ==="
	docker compose exec app php artisan test
