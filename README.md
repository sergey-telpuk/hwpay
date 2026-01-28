# HWPay

[![Tests](https://github.com/sergey-telpuk/hwpay/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/sergey-telpuk/hwpay/actions/workflows/tests.yml)
[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![codecov](https://codecov.io/gh/sergey-telpuk/hwpay/graph/badge.svg)](https://codecov.io/gh/sergey-telpuk/hwpay)

Symfony 8 + Docker Compose + RoadRunner + DDD + Temporal + MySQL + Redis.

## Stack

- **Symfony 8** — HTTP API
- **RoadRunner** — application server (HTTP)
- **MySQL** — app database
- **Redis** — idempotency and caching for high load
- **PostgreSQL** — Temporal persistence
- **Temporal** — workflows
- **PHPUnit** — tests

## Fund Transfer API

Secure API for transferring funds between accounts. Uses [moneyphp/money](https://github.com/moneyphp/money) for decimal currency handling, pessimistic locking for transaction integrity, and Redis for idempotency.

### Setup

1. Start services: `docker compose up -d`  
   On **first** MySQL start (empty volume), the test database `app_test` is created automatically (see `docker/mysql/init/01-create-test-db.sql`). If MySQL was already running before, run that SQL manually or recreate the volume.
2. Run migrations (one migration creates the `account` table): `docker compose run --rm app php bin/console doctrine:migrations:migrate --no-interaction`
3. (Optional) Create accounts: insert into `account` table with `id` (VARCHAR 36), `balance_minor` (BIGINT), `currency` (VARCHAR 3, e.g. USD, EUR). Transfers between different currencies use **conversion**: the amount is in the source account's currency and is converted to the target currency using configurable exchange rates (see `parameters.exchange_rates` in `config/services.yaml`).

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
  "transfer_id": "unique-key-per-request",
  "from_account_id": "account-uuid-1",
  "to_account_id": "account-uuid-2",
  "amount_minor": 10000
}
```

**Errors:**

- `400` — Invalid JSON or bad request.
- `404` — Account not found.
- `422` — Validation errors (see `errors` object) or insufficient balance (see `error`).

## Structure (DDD)

- `src/Domain/` — entities, value objects, domain logic
- `src/Application/` — use cases, ports (interfaces)
- `src/Infrastructure/` — HTTP controllers, Doctrine, Temporal workers

## Run with Docker

```bash
docker compose up -d
```

- App (Symfony via RoadRunner): http://localhost:8080
- Temporal UI: http://localhost:8088
- MySQL: localhost:3306 (user `app`, password `app`, databases `app` and `app_test` for tests)
- Redis: localhost:6379
- Temporal gRPC: localhost:7233

## Local development (without Docker)

1. Install PHP 8.4+ (with bcmath extension for moneyphp/money), Composer, MySQL, [RoadRunner binary](https://roadrunner.dev/docs/intro-install), Temporal server (e.g. [temporalite](https://docs.temporal.io/development-guide/run-your-app-locally)).

2. Install dependencies and RoadRunner binary:

```bash
composer install
./vendor/bin/rr get --location bin/
```

3. Copy `.env` and set `DATABASE_URL`, `TEMPORAL_ADDRESS`.

4. Run RoadRunner:

```bash
bin/rr serve
```

5. Run Temporal worker (in another terminal, if using workflows):

```bash
php bin/temporal-worker.php
```

## Tests

Tests require the `app_test` database (created automatically on first `docker compose up` when using Docker).

```bash
composer install
php bin/console doctrine:migrations:migrate --no-interaction --env=test
bin/phpunit
```

## Code quality

- **PHP_CodeSniffer** — check: `composer phpcs`; fix: `composer phpcbf`. Config: `phpcs.xml.dist`
- **Rector** — refactoring & upgrades: `composer rector:dry` (preview), `composer rector` (apply). Config: `rector.php`

## Temporal

- Workflow example: `App\Infrastructure\Temporal\HelloWorkflow`
- Activity: `App\Infrastructure\Temporal\HelloActivity`
- Worker script: `bin/temporal-worker.php` (run via RoadRunner temporal plugin or standalone when Temporal server is available).

Start a workflow from PHP:

```php
use Temporal\Client\WorkflowClient;
use Temporal\Client\GRPC\ServiceClient;
use App\Infrastructure\Temporal\HelloWorkflow;

$client = WorkflowClient::create(ServiceClient::create('127.0.0.1:7233'));
$run = $client->start(HelloWorkflow::class, 'World');
echo $run->getResult(); // "Hello, World!"
```
