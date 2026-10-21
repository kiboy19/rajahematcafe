# Gateway transaction schema baseline

Baseline captured from the migration source in `database/migrations/2026_10_20_171501_create_gateway_transactions_table.php`.

## Existing gateway_transactions columns

| Column | Type / attributes | Index or relationship |
|---|---|---|
| `id` | BIGINT UNSIGNED primary key | primary |
| `provider` | VARCHAR(30), required | composite index with `provider_reference` |
| `provider_reference` | VARCHAR(64), required | composite index with `provider` |
| `idempotency_key` | VARCHAR(64), required, unique | unique |
| `type` | VARCHAR(30), required | — |
| `status` | VARCHAR(20), required | index with `order_id` |
| `gross_amount` | BIGINT UNSIGNED, required | — |
| `fee_amount` | BIGINT UNSIGNED, default 0 | — |
| `net_amount` | BIGINT UNSIGNED, required | — |
| `payload` | JSON, nullable | legacy raw payload; read-only until backfilled to `payload_encrypted` |
| `signature` | VARCHAR(128), nullable | — |
| `customer_id` | BIGINT UNSIGNED, nullable | FK to `customers`, null on delete |
| `order_id` | BIGINT UNSIGNED, nullable | FK to `orders`, null on delete |
| `wallet_transaction_id` | BIGINT UNSIGNED, nullable | FK to `wallet_transactions`, null on delete |
| `settled_at` | TIMESTAMP, nullable | retained; written from provider `settlement_time` in UTC |
| `created_at`, `updated_at` | timestamps | — |

## Legacy configuration keys

`config/pos.php` contains the Midtrans keys `enabled`, `is_production`, `is_sandbox`, `paused`, `new_stack_enabled`, `shadow_mode_enabled`, `legacy_fallback_enabled`, `server_key`, `server_key_rotation`, `payload_key`, polling/buffer/retention controls, and deprecated `client_key`/`merchant_id`. Environment values are `POS_MIDTRANS_*`, `POS_PAYLOAD_KEY`, and legacy `MIDTRANS_*` names as documented in that config file.

## Compatibility mapping

| Legacy | Release mapping |
|---|---|
| `payload` | Retained and read-only until encrypted backfill populates `payload_encrypted`. |
| `provider_reference` | Retained; semantic alias of `midtrans_order_id`. Backfill copies it into `midtrans_order_id`; no rename. |
| `settled_at` | Retained; populated from provider `settlement_time` normalized to UTC. |

No existing column is dropped or renamed in this release. Drops are deferred to release N+2 and require a separately approved migration.

## Operational baseline note

`php artisan db:show` was attempted after installing Composer dependencies, but this checkout has no `database/database.sqlite` file and no configured external database. The migration scan above is therefore the authoritative source baseline for this checkout; run `php artisan migrate` against the target environment before production rollout.
