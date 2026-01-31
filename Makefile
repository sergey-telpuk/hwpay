.PHONY: help install test phpstan phpcs phpcbf rector rector-dry qa check migrate cache-clear cache-clear-test

.DEFAULT_GOAL := help

# One-off commands: docker compose run --rm <service> ...
APP_RUN := docker compose run --rm app
APP_TEST := docker compose run --rm -e APP_ENV=test -e APP_SECRET=test-secret -e DATABASE_URL="mysql://app:app@mysql:3306/app_test?serverVersion=8.4" -e REDIS_URL=redis://redis:6379 app

help:
	@echo "HWPay — all commands via Docker Compose"
	@echo ""
	@echo "  make install       - composer install (in container)"
	@echo "  make test         - run PHPUnit (in container)"
	@echo "  make phpstan      - run PHPStan (in container)"
	@echo "  make phpcs        - run PHP_CodeSniffer (in container)"
	@echo "  make phpcbf       - fix code style (in container)"
	@echo "  make rector-dry   - Rector dry-run (in container)"
	@echo "  make rector       - Rector apply (in container)"
	@echo "  make qa           - phpstan + phpcs + rector-dry + test (in container)"
	@echo "  make migrate      - doctrine:migrations:migrate (in container)"
	@echo "  make cache-clear  - Symfony cache:clear (in container)"

# --- Docker Compose (default) ---

install:
	$(APP_RUN) composer install

test:
	$(APP_TEST) sh -c "php bin/console cache:clear --env=test --no-warmup && php vendor/bin/phpunit"

phpstan:
	$(APP_TEST) sh -c "php bin/console cache:clear --env=test --no-warmup && composer phpstan"

phpcs:
	$(APP_RUN) composer phpcs

phpcbf:
	$(APP_RUN) composer phpcbf

rector:
	$(APP_RUN) composer rector

rector-dry:
	$(APP_RUN) composer rector:dry

qa: phpstan phpcs rector-dry test

check: qa

migrate:
	$(APP_RUN) php bin/console doctrine:migrations:migrate --no-interaction

cache-clear:
	$(APP_RUN) php bin/console cache:clear --no-warmup

cache-clear-test:
	$(APP_TEST) php bin/console cache:clear --env=test --no-warmup
