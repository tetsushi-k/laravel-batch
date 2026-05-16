# =============================================================
# Makefile - Laravel Batch Monthly Report
# Usage: make <target>
# =============================================================

.PHONY: help setup up down restart bash migrate seed batch batch-force worker logs ps

help:
	@echo ""
	@echo "Laravel Batch Monthly Report - Available commands"
	@echo "=================================================="
	@echo "  make setup        First-time setup (install, key:generate, migrate, seed)"
	@echo "  make up           Start containers"
	@echo "  make down         Stop containers"
	@echo "  make restart      Restart containers"
	@echo "  make bash         Open shell in app container"
	@echo "  make migrate      Run migrations"
	@echo "  make seed         Seed test data"
	@echo "  make batch        Run monthly report batch"
	@echo "  make batch-force  Force re-run (ignore idempotency check)"
	@echo "  make worker       Start queue worker (foreground)"
	@echo "  make logs         Tail container logs"
	@echo "  make ps           Show container status"
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
	@echo "Check mail queue: make worker"

batch-force:
	@echo "=== Force running monthly report batch ==="
	docker compose exec app php artisan app:monthly-report --force

worker:
	@echo "=== Starting queue worker (Ctrl+C to stop) ==="
	docker compose exec app php artisan queue:work --verbose

logs:
	docker compose logs -f

ps:
	docker compose compose ps
