.PHONY: help setup dev test lint fix analyse check build clean fresh queue schedule

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-10s\033[0m %s\n", $$1, $$2}'

setup: ## Install dependencies, prepare .env, migrate and build assets
	@echo "→ PHP dependencies"
	composer install
	@echo "→ JS dependencies"
	npm ci
	@test -f .env || cp .env.example .env
	@php artisan key:generate --ansi
	@echo "→ Database"
	php artisan migrate --seed
	@echo "→ Assets"
	npm run build
	@echo "Setup complete. Run 'make dev'."

dev: ## Run the dev server and the Vite watcher together
	php artisan serve --port=8085 & npm run dev

queue: ## Run a queue worker across all lanes
	php artisan queue:work --queue=high,default,low --tries=3

schedule: ## Run the scheduler in the foreground (production uses cron)
	php artisan schedule:work

test: ## Run the test suite
	vendor/bin/pest

lint: ## Check code style
	vendor/bin/pint --test

fix: ## Fix code style
	vendor/bin/pint

analyse: ## Run static analysis
	vendor/bin/phpstan analyse --memory-limit=1G

check: lint analyse test ## Run every gate CI runs

build: ## Build production assets
	npm run build

fresh: ## Drop and rebuild the database with seed data
	php artisan migrate:fresh --seed

clean: ## Clear caches and built assets
	php artisan optimize:clear
	rm -rf public/build build/phpstan
