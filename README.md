# HWPay — Fund Transfer API

[![Tests](https://github.com/sergey-telpuk/hwpay/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/sergey-telpuk/hwpay/actions/workflows/tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/sergey-telpuk/hwpay/tests.yml?branch=main&label=PHPStan)](https://github.com/sergey-telpuk/hwpay/actions/workflows/tests.yml)
[![PHPCS](https://img.shields.io/github/actions/workflow/status/sergey-telpuk/hwpay/tests.yml?branch=main&label=PHPCS)](https://github.com/sergey-telpuk/hwpay/actions/workflows/tests.yml)
[![Rector](https://img.shields.io/github/actions/workflow/status/sergey-telpuk/hwpay/tests.yml?branch=main&label=Rector)](https://github.com/sergey-telpuk/hwpay/actions/workflows/tests.yml)
[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://www.php.net/)

A secure REST API for transferring funds between accounts. Built with PHP 8.5, Symfony 8, MySQL, Redis, and Docker Compose.

---

## Table of contents

- [Prerequisites](#prerequisites)
- [Getting started](#getting-started)
- [API](#api)
- [Stack](#stack)
- [Project structure](#project-structure)
- [Development](#development)
  - [Connecting to the database](#connecting-to-the-database-local-inspection)
- [Architecture](#architecture)
- [Deployment](#deployment)
- [Possible improvements](#possible-improvements)

---

## Prerequisites

- **Docker** and **Docker Compose**
- No local PHP or Composer required; all commands run inside containers.

---

## Getting started

### 1. Clone and start services

```bash
git clone https://github.com/sergey-telpuk/hwpay.git
cd hwpay
docker compose up -d
```

Wait a few seconds for MySQL and Redis. API base URL: **http://localhost:8080**.

### 2. Run migrations

```bash
make migrate
```

Without Make: `docker compose run --rm app php bin/console doctrine:migrations:migrate --no-interaction`

### 3. (Optional) Seed test accounts

For manual testing of `POST /api/transfer` with pre-funded accounts:

```bash
docker compose run --rm app php bin/console app:seed-manual-test-accounts
```

Creates:

- `...000010` — 100.00 USD  
- `...000011` — 50.00 USD  
- `...000020` — 200.00 USD (for cross-currency)  
- `...000021` — 0 EUR (target for USD→EUR transfer)  

### 4. Smoke test

- **Health:** `GET http://localhost:8080/health` → `200`, `{"status":"ok"}`
- **Transfer:** `POST http://localhost:8080/api/transfer` with JSON body (see [API](#api)); use the account IDs from step 3 or your own.

Example with cURL:

```bash
curl -s -X POST http://localhost:8080/api/transfer \
  -H "Content-Type: application/json" \
  -d '{"from_account_id":"00000000-0000-0000-0000-000000000010","to_account_id":"00000000-0000-0000-0000-000000000011","amount_minor":1000,"idempotency_key":"demo-1"}'
```

You can also use `http/transfer.http` in PhpStorm or VS Code.

### 5. Run tests and QA

```bash
make test   # PHPUnit (test DB and migrations are used automatically)
make qa     # PHPStan + PHPCS + Rector dry-run + tests (same as CI)
```

---

## API

All API errors (including 5xx) are returned as JSON for `/api/*` and when `Accept: application/json` is sent.

### Health

| Method | URL           | Description   |
|--------|---------------|---------------|
| GET    | `/health`     | Liveness check (DB + cache) |

**Response (200):** `{"status":"ok","checks":{"database":true,"cache":true}}`  
**Response (503):** when DB or cache is unreachable — `{"status":"degraded","checks":{"database":false,"cache":false}}` (or one false).

### Transfer funds

| Method | URL            | Description      |
|--------|----------------|------------------|
| POST   | `/api/transfer`| Transfer between accounts |

**Request (JSON):**

| Field            | Type   | Required | Description |
|------------------|--------|----------|-------------|
| `from_account_id`| string | yes     | Source account UUID (valid UUID format required) |
| `to_account_id` | string | yes     | Target account UUID (valid UUID format required) |
| `amount_minor`   | number | yes     | Amount in smallest unit (e.g. cents) |
| `idempotency_key`| string | yes     | Unique key per logical transfer (duplicates return same result) |

Example:

```json
{
  "from_account_id": "00000000-0000-0000-0000-000000000010",
  "to_account_id": "00000000-0000-0000-0000-000000000011",
  "amount_minor": 1000,
  "idempotency_key": "unique-key-per-request"
}
```

**Response (200):**

```json
{
  "transfer_id": "uuid",
  "from_account_id": "uuid",
  "to_account_id": "uuid",
  "amount_minor": 1000,
  "currency": "USD"
}
```

**Error responses:** All include `code` and `error` (validation also has `errors`).

| Status | Code | Meaning |
|--------|------|---------|
| 400 | `INVALID_JSON`, `INVALID_PAYLOAD`, `INVALID_ARGUMENT` | Invalid JSON, extra/missing fields, invalid UUID, same from/to, or bad request (e.g. missing FX rate) |
| 404 | `ACCOUNT_NOT_FOUND` | Account not found (from or to) |
| 422 | `VALIDATION_FAILED`, `INSUFFICIENT_BALANCE` | Validation errors (`errors` object) or insufficient balance (`error` string) |
| 500 | `INTERNAL_ERROR` | Server error (body has `code`, `error`, and optional `detail` in dev) |

---

## Stack

| Layer    | Technology |
|----------|------------|
| API      | Symfony 8  |
| Server   | RoadRunner |
| Database | MySQL 8    |
| Cache    | Redis (idempotency) |
| Tests    | PHPUnit 11 |

Uses [moneyphp/money](https://github.com/moneyphp/money) and [yceruto/money-bundle](https://github.com/yceruto/money-bundle) for currency; pessimistic locking and double-entry ledger for integrity.

---

## Project structure

```
src/
├── Domain/         # Entities, value objects, domain exceptions
├── Application/    # Use cases, ports (interfaces)
└── Infrastructure/ # HTTP, Doctrine, Redis, console
```

See [Architecture](#architecture) for design details.

---

## Development

### Commands (all via Docker)

| Command       | Description |
|---------------|-------------|
| `make install`| Composer install |
| `make test`   | PHPUnit |
| `make qa`     | PHPStan + PHPCS + Rector dry-run + tests |
| `make migrate`| Run Doctrine migrations |
| `make phpstan`| PHPStan only |
| `make phpcs`  | PHP_CodeSniffer (check) |
| `make phpcbf` | PHP_CodeSniffer (fix) |
| `make rector-dry` | Rector preview |
| `make rector` | Rector apply |
| `make cache-clear` | Clear Symfony cache |

Run `make help` for the full list.

### Connecting to the database (local inspection)

With `docker compose up -d`, MySQL is exposed on **localhost:3306**. Use these settings in any client (DBeaver, TablePlus, MySQL Workbench, DataGrip, etc.):

| Setting   | Value    |
|-----------|----------|
| Host      | `localhost` |
| Port      | `3306`   |
| User      | `app`    |
| Password  | `app`    |
| Database  | `app` (main) or `app_test` (tests) |

**Connection string (JDBC/ODBC):**  
`jdbc:mysql://localhost:3306/app?user=app&password=app`

**CLI (from host):**
```bash
mysql -h 127.0.0.1 -P 3306 -u app -papp app
```
(Password is `app`; use `app_test` instead of `app` for the test database.)

**CLI (inside Docker):**
```bash
docker compose exec mysql mysql -u app -papp app
```

### Migrations

- **Apply:** `make migrate`
- **Generate after entity changes:**  
  `docker compose run --rm app php bin/console doctrine:migrations:diff`  
  Then run `make migrate`.
- **Reset test DB** (if tests fail with missing tables or migration conflicts):  
  `docker compose run --rm -e APP_ENV=test app php bin/console doctrine:schema:drop --full-database --force --env=test`  
  then run migrations for test env and `make test`.

Test database `app_test` is created on first `docker compose up` (see `docker/mysql/init/01-create-test-db.sql`). Migrations run automatically before tests via `tests/bootstrap.php`.

### Code quality

- **PHPStan:** `make phpstan` — config: `phpstan.neon`
- **PHP_CodeSniffer:** `make phpcs` / `make phpcbf` — config: `phpcs.xml.dist`
- **Rector:** `make rector-dry` / `make rector` — config: `rector.php`

---

## Architecture

- **DDD:** Domain, Application (ports), Infrastructure (adapters).
- **Double-entry ledger:** Balances from `ledger_entries`; no stored balance column.
- **Holds:** Each transfer creates a hold (Active → Captured or Released). Available balance = ledger balance − active holds.
- **FX:** Cross-currency transfers use technical FX accounts and 4 ledger entries.
- **Idempotency:** `idempotency_key` stored in DB (`transactions.external_id`) and Redis (24h TTL).
- **Concurrency:** Pessimistic lock (`FOR UPDATE`) on both accounts in deterministic order (by ID) to avoid deadlocks.
- **Error handling:** Failed transfers persist transaction as Failed and hold as Released when possible; API returns JSON errors for `/api/*`.

Exchange rates are configured in `config/services.yaml` under `parameters.exchange_rates`.

---

## Deployment

1. Run migrations on deploy so the schema is up to date:
   ```bash
   make migrate
   ```
   Or: `docker compose run --rm app php bin/console doctrine:migrations:migrate --no-interaction`  
   Doctrine runs only pending migrations; safe to run on every deploy.

2. Ensure Redis and MySQL are available at the URLs configured in `DATABASE_URL` and `REDIS_URL`.

---

## Possible improvements

- **Rate limiting** and **authentication** (e.g. API key or JWT) on `/api/transfer`
- **Pagination and filters** for “list transfers” or “list ledger entries”
- **Saga/outbox** for async steps (e.g. external notifications) with consistency guarantees
- **Workflow engine** (e.g. [Temporal](https://temporal.io/)) for event-driven or multi-service flows
- **Scheduled job** to mark expired holds (already excluded from available balance by query)
- **Metrics** (e.g. Prometheus) for throughput, latency, errors

---

## Submission (task requirements)

- **Install & run:** [Getting started](#getting-started) above.
- **Time spent:** ~8 hours
- **AI tools used:** Cursor, ChatGPT
