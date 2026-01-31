# HWPay — Analysis and Improvement Ideas

Analysis of the codebase with concrete, prioritized suggestions.

---

## 1. Security

| Improvement | Priority | Effort | Notes |
|-------------|----------|--------|-------|
| **Authentication** on `POST /api/transfer` | High | Medium | API key (header) or JWT; reject unauthenticated requests with 401. |
| **Rate limiting** | High | Low–Medium | Per IP or per API key; e.g. 100 req/min to avoid abuse and DoS. |
| **Validate account IDs as UUIDs** | Medium | Low | In `TransferController` or `AccountId`: accept only UUID v4 format; return 400 with clear message instead of 404/500 from DB. |
| **Secrets** | Low | Low | Ensure `APP_SECRET` and Redis URL are never committed; document in README. |

---

## 2. API and DX

| Improvement | Priority | Effort | Notes |
|-------------|----------|--------|-------|
| **API versioning** | Medium | Low | e.g. `/api/v1/transfer`; allows future breaking changes without breaking clients. |
| **Request DTO** | Low | Low | Replace raw `$payload` array with a value object (e.g. `TransferRequest`) for type safety and single place of validation. |
| **Response: include currency** | Low | Low | e.g. `"amount_minor": 1000, "currency": "USD"` so client does not need to infer. |
| **OpenAPI / Swagger** | Low | Medium | Document endpoints, request/response, errors; generate spec from attributes or maintain YAML. |
| **Structured error codes** | Low | Low | e.g. `"code": "ACCOUNT_NOT_FOUND"` in addition to `"error": "Account not found: ..."` for client-side handling. |

---

## 3. Domain and Application Layer

| Improvement | Priority | Effort | Notes |
|-------------|----------|--------|-------|
| **Decouple handler from Doctrine entities** | Medium | High | `TransferFundsHandler` currently uses `HoldEntity`, `TransactionEntity`, `LedgerEntryEntity`, `FxTransactionEntity` directly. Introduce persistence ports (e.g. `TransferPersistenceInterface::persistTransaction`, `persistHold`, `persistLedgerEntries`) and keep handler dependent only on interfaces; move entity creation into Infrastructure. |
| **AccountId UUID format** | Medium | Low | In `AccountId` constructor, validate UUID format (e.g. regex or `Uuid::fromString` + catch); throw domain exception with clear message. |
| **Exchange rate not found** | Low | Low | Already throws `InvalidArgumentException`; controller returns 400. Optionally introduce `ExchangeRateNotFoundException` and map to 503 if rate is “temporarily unavailable” in future. |
| **Idempotency key format** | Low | Low | Optional: restrict to alphanumeric + hyphen (e.g. `[a-zA-Z0-9-]{1,128}`) to avoid injection or weird cache keys. |

---

## 4. Testing

| Improvement | Priority | Effort | Notes |
|-------------|----------|--------|-------|
| **Unit tests for `TransferFundsHandler`** | High | Medium | Isolate handler with mocks (accounts, idempotency, EM, logger); test balance check, lock order, idempotency return, same-account validation, insufficient balance, FX path. |
| **Unit tests for `Account`** | Medium | Low | Test `debit`/`credit` and exceptions (negative amount, insufficient balance). |
| **Integration test: extra fields → 422** | Medium | Low | Send payload with unknown field; assert 422 and `errors` structure. |
| **Integration test: idempotency after Failed transfer** | Medium | Low | Simulate failed transfer (e.g. throw after persist); retry with same key; assert second request succeeds and returns new transfer (or document that retry returns same failed result if that is desired). |
| **CI: optional Redis service** | Low | Low | Test env uses filesystem cache; CI works without Redis. Add Redis to CI only if you want to run the same cache backend as prod. |

---

## 5. Observability and Operations

| Improvement | Priority | Effort | Notes |
|-------------|----------|--------|-------|
| **Structured logging** | Medium | Low | Already logging transfer success and failure; ensure all log entries are structured (e.g. JSON) and include correlation id / request id if available. |
| **Health check: DB + Redis** | Medium | Low | `/health` could run a trivial DB query and Redis ping; return 503 if either fails so load balancer can stop sending traffic. |
| **Metrics** | Low | Medium | e.g. Prometheus: count of transfers, latency histogram, errors by type; expose `/metrics` behind auth. |
| **Tracing** | Low | High | OpenTelemetry or similar for request and transfer flow across services. |

---

## 6. Code Quality and Maintainability

| Improvement | Priority | Effort | Notes |
|-------------|----------|--------|-------|
| **Simplify controller exception handling** | Low | Low | Single `catch (\Throwable)` and map known exceptions (AccountNotFoundException → 404, etc.); reduce duplication between `HandlerFailedException` and direct catches. |
| **Docblocks for interfaces** | Low | Low | Add `@throws` to `AccountRepositoryInterface`, `ExchangeRateProviderInterface` where applicable. |
| **`.env.example`** | Low | Low | List `APP_SECRET`, `DATABASE_URL`, `REDIS_URL` with placeholder values for new developers. |
| **Rector / PHPStan** | Done | — | Already at high level; keep rules and fix new issues. |

---

## 7. Suggested Quick Wins (low effort, high value)

1. **Validate account IDs as UUIDs** in controller or `AccountId` → 400 with clear message.
2. **Integration test for extra fields** → 422; protects strict validation.
3. **Health check** that pings DB (and optionally Redis).
4. **`.env.example`** with required env vars.
5. **Simplify controller catch** into one block with exception → status/body mapping.

---

## 8. Out of Scope for This Doc

- Rate limiting and auth implementation details.
- Full CQRS/event sourcing.
- Multi-tenant or per-currency rate limits.
- Non-functional requirements (SLA, RTO/RPO).
