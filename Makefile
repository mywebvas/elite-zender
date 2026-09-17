.PHONY: setup dev test build lint

setup:
	@echo "Installing PHP dependencies..."
	composer install
	@echo "Installing JS dependencies..."
	npm install
	@echo "Setting up database..."
	touch database/database.sqlite
	php artisan migrate:fresh --seed
	@echo "Building assets..."
	npm run build
	@echo "Setup complete! Run 'make dev' to start the application."

dev:
	php artisan serve & npm run dev

test:
	php artisan test

build:
	npm run build

clean:
	php artisan optimize:clear
	rm -rf public/build
