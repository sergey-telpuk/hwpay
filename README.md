# HWPay

Symfony 8 + Docker Compose + RoadRunner + DDD + Temporal + MySQL.

## Stack

- **Symfony 8** — HTTP API
- **RoadRunner** — application server (HTTP)
- **MySQL** — app database
- **PostgreSQL** — Temporal persistence
- **Temporal** — workflows
- **PHPUnit** — tests

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
- MySQL: localhost:3306 (user `app`, password `app`, database `app`)
- Temporal gRPC: localhost:7233

## Local development (without Docker)

1. Install PHP 8.4+, Composer, MySQL, [RoadRunner binary](https://roadrunner.dev/docs/intro-install), Temporal server (e.g. [temporalite](https://docs.temporal.io/development-guide/run-your-app-locally)).

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

```bash
composer install
./vendor/bin/phpunit
```

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
