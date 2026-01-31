# HWPay — Fund Transfer API

[![Tests](https://github.com/sergey-telpuk/hwpay/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/sergey-telpuk/hwpay/actions/workflows/tests.yml)
[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://www.php.net/)

A secure API for transferring funds between accounts. PHP 8.5, Symfony 8, MySQL, Redis, Docker Compose.

### Submission (task requirements)

- **Install & run:** see [How to install and run](#how-to-install-and-run) below.
- **Time spent:** ~8 hours
- **AI tools and prompts used:** Cursor, ChatGPT

---

## How to install and run

**Prerequisites:** Docker and Docker Compose.

```bash
git clone https://github.com/sergey-telpuk/hwpay.git
cd hwpay
docker compose up -d
docker compose run --rm app php bin/console doctrine:migrations:migrate --no-interaction
```

- **API:** http://localhost:8080  
- **Health:** `GET http://localhost:8080/health`  
- **Transfer:** `POST http://localhost:8080/api/transfer` (see [Fund Transfer API](#fund-transfer-api) below)

**Run tests:**

```bash
docker compose run --rm -e APP_ENV=test app php vendor/bin/phpunit
```

---

## Stack

- **Symfony 8** — HTTP API
- **RoadRunner** — application server (HTTP)
- **MySQL** — app database
- **Redis** — idempotency and caching for high load
- **PHPUnit** — tests

## Fund Transfer API

Secure API for transferring funds between accounts. Uses [moneyphp/money](https://github.com/moneyphp/money) for currency handling, [yceruto/money-bundle](https://github.com/yceruto/money-bundle) for Doctrine integration (Embedded Money), pessimistic locking for transaction integrity, and Redis for idempotency.

### Setup

1. Start services: `docker compose up -d`  
   On **first** MySQL start (empty volume), the test database `app_test` is created automatically (see `docker/mysql/init/01-create-test-db.sql`). If MySQL was already running before, run that SQL manually or recreate the volume.
2. Run migrations: `composer migrations:migrate` or `docker compose run --rm app php bin/console doctrine:migrations:migrate --no-interaction`  
   If you see "previously executed migrations that are not registered", remove orphaned entries with `php bin/console doctrine:migrations:version 'DoctrineMigrations\VersionXXXX' --delete` (run in app container or locally; use `--env=test` for test DB).

**Generating migrations from entities (Symfony):** after changing entity mappings, generate a new migration by comparing schema to the current DB:

```bash
composer migrations:diff
```

Or in Docker: `docker compose exec app php bin/console doctrine:migrations:diff`. This creates a new file in `migrations/` with the required SQL. Then run `composer migrations:migrate` to apply it.

**One migration only via `doctrine:migrations:diff`:** to get a single migration that contains the full schema (instead of incremental diffs), run diff against an empty database: drop the DB (or use a fresh one), create it, then run diff. Example:

```bash
docker compose run --rm app php bin/console doctrine:database:drop --force --if-exists
docker compose run --rm app php bin/console doctrine:database:create --if-not-exists
docker compose run --rm app php bin/console doctrine:migrations:diff --no-interaction
```

This generates one migration with all `CREATE TABLE` statements. The project’s single migration also adds foreign keys and the two technical FX accounts (inserts) manually, because entities use plain UUID columns and diff does not emit data.
3. (Optional) Create accounts: insert into `accounts` table (`id` UUID, `owner_type`, `owner_id`, `currency`, `type`, `status`, `created_at`). Balance is derived from `ledger_entries` minus active `holds`; to fund an account you add ledger entries (see tests for seeding examples). Cross-currency transfers use configurable rates in `config/services.yaml` (`parameters.exchange_rates`).

### POST /api/transfer

Transfer funds from one account to another.

**Request** (JSON):

```json
{
  "from_account_id": "account-uuid-1",
  "to_account_id": "account-uuid-2",
  "amount_minor": 10000,
  "idempotency_key": "unique-key-per-request"
}
```

- `amount_minor` — amount in smallest currency unit (e.g. cents).
- `idempotency_key` — unique key per logical transfer; duplicate requests with the same key return the same result (stored in Redis 24h).

**Response** (200):

```json
{
  "transfer_id": "uuid-of-the-created-transaction",
  "from_account_id": "account-uuid-1",
  "to_account_id": "account-uuid-2",
  "amount_minor": 10000
}
```

**Errors:**

- `400` — Invalid JSON, empty body, same from/to account, or bad request (e.g. no FX rate).
- `404` — Account not found (from or to).
- `422` — Validation errors (see `errors` object) or insufficient balance (see `error`).

## Architecture and design

- **DDD-style layout:** `Domain` (Account, exceptions, enums), `Application` (commands, handlers, ports), `Infrastructure` (HTTP, Doctrine, repositories).
- **Double-entry ledger:** Balances are computed from `ledger_entries` (debit/credit per account and currency). No stored balance column; integrity via sum of entries.
- **Holds:** Each transfer creates a hold (Active) on the source account; on success it is set to Captured, on failure to Released. Available balance = ledger balance − active (non-expired) holds.
- **FX:** Cross-currency transfers create 4 ledger entries via technical FX accounts (sold-currency leg and bought-currency leg) so each currency balances; plus one `fx_transactions` row for rate/spread. See `docs/FX_LEDGER.md`.
- **Idempotency:** By `idempotency_key` (stored in `transactions.external_id` and in Redis for cache). Duplicate request returns the same result without double-spend.
- **Concurrency:** Pessimistic lock (`FOR UPDATE`) on both accounts in a deterministic order (by account id) to avoid deadlocks.
- **Errors:** On handler failure after creating the hold, if the EntityManager is still open we persist the transaction as Failed and the hold as Released for audit; then rethrow.

**Implementation:** The code follows the above flow. Idempotency is implemented via `TransactionRepository::findOneByExternalId` (DB column `transactions.external_id`) and Redis cache in the controller (24h TTL). Balance and holds: `AccountRepository::toAccount` uses `LedgerRepository::getBalanceForAccount` minus `HoldRepository::getActiveHoldsSum` (only Active holds with `expires_at` null or future). Lock order and hold/transaction status transitions are in `TransferFundsHandler`; FX entries in `persistFxLedgerEntries`. See `docs/FX_LEDGER.md` for the 4-entry FX table.

## Possible improvements

- **Rate limiting** and **auth** (e.g. API key or JWT) on `/api/transfer`.
- **Pagination and filters** for a future “list transfers” or “list ledger entries” endpoint.
- **Saga/outbox** if we introduce async steps (e.g. notify external system) to keep consistency.
- **Async manipulation and interaction between microservices:** for event-driven or multi-service architecture, [Temporal](https://temporal.io/) (or similar) would be useful — workflows, retries, compensation.
- **Scheduled job** to set expired holds to status Expired (we already exclude them from available balance by `expires_at` in the query).
- **Metrics** (e.g. Prometheus) for transfer count, latency, errors.

## Structure (DDD)

- `src/Domain/` — entities, value objects, domain logic
- `src/Application/` — use cases, ports (interfaces)
- `src/Infrastructure/` — HTTP controllers, Doctrine

## Run with Docker

```bash
docker compose up -d
```

- App (Symfony via RoadRunner): http://localhost:8080
- MySQL: localhost:3306 (user `app`, password `app`, databases `app` and `app_test` for tests)
- Redis: localhost:6379

## Local development (without Docker)

1. Install PHP 8.4+ (with bcmath extension for moneyphp/money), Composer, MySQL, [RoadRunner binary](https://roadrunner.dev/docs/intro-install).

2. Install dependencies and RoadRunner binary:

```bash
composer install
./vendor/bin/rr get --location bin/
```

3. Copy `.env` and set `DATABASE_URL`, `REDIS_URL`.

4. Run RoadRunner:

```bash
bin/rr serve
```

## Tests

Tests require the `app_test` database (created automatically on first `docker compose up` when using Docker). Migrations run automatically before tests (via `tests/bootstrap.php`).

```bash
composer install
bin/phpunit
```

If tests fail with **Table 'app.accounts' doesn't exist** or "previously executed migrations that are not registered", reset the test DB and run migrations from scratch:

```bash
composer test:db-reset
bin/phpunit
```

In Docker: `docker compose exec app composer test:db-reset` then run your test command.

## Deployment

During deployment, run migrations so the database schema is up to date. Doctrine tracks executed migrations in the `doctrine_migration_versions` table and runs only those not yet applied on that database. See [DoctrineMigrationsBundle — Running Migrations during Deployment](https://symfony.com/bundles/DoctrineMigrationsBundle/current/index.html#running-migrations-during-deployment).

```bash
composer migrations:migrate
```

Run this after deploying the code and before (or when) starting the app. Safe to run on every deploy: only pending migrations are executed.

## Code quality

Run all checks: `composer qa` (PHPStan + PHP_CodeSniffer).

- **PHPStan** — static analysis: `composer phpstan`. Config: `phpstan.neon`. Run `php bin/console cache:clear` first if Symfony container is missing.
- **PHP_CodeSniffer** — PSR-12: `composer phpcs`; auto-fix: `composer phpcbf`. Config: `phpcs.xml.dist`
- **Rector** — refactoring & upgrades: `composer rector:dry` (preview), `composer rector` (apply). Config: `rector.php`
