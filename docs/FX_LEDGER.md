# FX and ledger entries

## Transfer flow (summary)

1. **Idempotency**: if `transactions.external_id` = idempotency key exists → return existing result.
2. **Lock** both accounts (deterministic order by id), **balance** = ledger − active holds (non-expired); check available ≥ debit amount.
3. **Hold**: create `holds` row (status `active`, reason `transfer`) on source account → reserves amount.
4. **Try**: resolve FX rate if cross-currency, debit/credit in memory, persist `transactions` (Pending) + `ledger_entries` (+ `fx_transactions` if FX), set hold→`captured`, transaction→`completed`, flush.
5. **On error**: if EntityManager open → set hold→`released`, transaction→`failed`, detach partial ledger/fx, persist transaction, flush; then rethrow.

## 4.1 fx_transactions

One row per FX deal: links to `transactions(id)` via `transaction_id` (unique). Stores base/quote currencies and amounts, fixed `rate`, and `spread`. No recalculation after insert.

## 4.2 Ledger entries for FX

For each FX deal, **4 rows** in `ledger_entries` with the same `transaction_id` (plus one row in `fx_transactions`):

| Account     | Side   | Currency       | Amount        |
|-------------|--------|----------------|---------------|
| from (user) | debit  | sold_currency  | debit_amount  |
| FX_DEBIT    | credit | sold_currency  | debit_amount  |
| FX_CREDIT   | debit  | bought_currency| credit_amount |
| to (user)   | credit | bought_currency| credit_amount |

Technical FX accounts: `FX_DEBIT` (00000000-0000-0000-0000-000000000001), `FX_CREDIT` (00000000-0000-0000-0000-000000000002). Created by migration; each currency leg is balanced (debit = credit per currency).

## Invariants

- **Currency**: `ledger_entries.currency` must equal the account’s currency (enforce in application or trigger).
- **Rate**: Fixed at insert in `fx_transactions`; no updates or recalculation.
- **FX profit**: `spread × base_amount`; post separately (e.g. fee ledger entries) if needed.
