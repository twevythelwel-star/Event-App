# Database schema and data migration

Review date: 1 November 2026. The schema was inspected as committed, by running every migration on an empty database and listing each table, index and foreign key, before anything was changed. Engines: SQLite for development and tests; MySQL 8 for production, with its own CI job.

## Findings in the committed schema

- **No duplicate models.** There is one model each for events, locations (`UsState`, `TargetMarket`), money (`App\Support\Money` and `Currency`, which are code, not tables), tickets (tiers on the event, codes on the order), orders and payments (`Order`, `OrderItem`, `OrderCharge`, `OrderAdjustment`, `CashPayment`), and roles (`UserRole`, `OrganizerUser`, `EventWorkerAssignment`). The three per-organizer settings tables each hold different settings:
  - `host_event_settings`: event defaults;
  - `host_payment_policies`: payment instructions and refunds;
  - `host_operational_rules`: food preparation.
- **Duplicate indexes:** four plain indexes repeated a unique index on the same column: `orders.ticket_code`, `orders.order_number`, `cash_payments.receipt_number` and `organizers.slug`.
- **Missing indexes for frequent filters:**
  - published events by date (discovery);
  - an event's orders by status (dashboards, reminders, event-change messages);
  - a person's assignment on an event (every staff and terminal check);
  - open disputes per order;
  - an event's active menu;
  - an actor's audit trail.
- **Missing foreign keys:**
  - `notification_logs.order_id` (which was a 255-character string, not a UUID) and `notification_logs.organizer_id`;
  - `order_adjustments.organizer_id`;
  - `charge_rules.organizer_id` and `charge_rules.event_id`.
- **No database-level money rules:** nothing at the database level stopped a negative amount, or a refund larger than the amount paid.
- **Currency:** every financial table (orders, order items, charges, adjustments, cash payments, disputes) already has a NOT NULL `currency`. No row has a blank currency.

## Migration `2026_11_01_000033_tighten_indexes_keys_and_money_checks`

It is forward-only: it adds no table and changes no row.

| Change | Detail |
|---|---|
| Removed | The four duplicate plain indexes; the unique indexes stay |
| Indexes added | `events_status_date_idx`, `orders_event_status_idx`, `ewa_event_user_idx`, `disputes_order_status_idx`, `menu_items_event_active_idx`, `audit_user_created_idx` |
| `notification_logs.order_id` | Becomes a UUID column like every other order reference (MySQL needs matching key types) |
| Foreign keys | `notification_logs` → `orders`, and → `organizers`: **set null on delete**. The log keeps the order number and ticket code itself, so the history stays readable. `order_adjustments` → `organizers`, and `charge_rules` → `organizers` / `events`: **cascade**, as orders and menus already do. |
| CHECK constraints (MySQL) | Order amounts ≥ 0; refunded ≤ paid; item quantities ≥ 0 and collected ≤ ordered; item, charge and dispute amounts ≥ 0; adjustment amounts > 0. Cash rows are excluded because reversals are stored as negative amounts. |
| Names | Short and explicit, all within MySQL's 64-character limit (a test checks this) |

**Safety.** Before adding a key or constraint, the migration checks the existing rows. If any row would break it, the migration stops and names the table and the count. It never deletes, clears or rewrites data to make a constraint fit. `down()` removes only what `up()` added.

**SQLite.** SQLite cannot add CHECK constraints to an existing table, so development databases rely on the application's own checks, which the existing payment and refund tests cover. SQLite drops foreign keys by column, so `down()` handles both engines.

## Testing

| Scenario | Result |
|---|---|
| Fresh install, SQLite (`migrate --seed` on an empty database) | Passed; the schema differs from before only by the changes listed above |
| Upgrade, SQLite (a copy of the development database, with its 8 historical JMD orders) | Passed; every table's row count, money totals and currency list were identical before and after |
| Rollback and re-apply, SQLite | Passed; rollback restores exactly the previous indexes and keys; data unchanged throughout |
| Full PHPUnit suite and legacy runner (PHPUnit migrates from scratch for every test) | Passed: PHPUnit 271/271, legacy runner 526/526, lint clean, `migrate:fresh --seed` |
| MySQL 8 | **Not run locally** (no MySQL on this machine). The CI job `backend-mysql` runs the migrations and tests on MySQL 8.0 on push; that is the first real run of the CHECK statements. |

`SchemaIntegrityTest` covers:
- the new indexes, the removed duplicates and the name lengths;
- message history kept when an order is deleted;
- a fee rule refused for an organizer that doesn't exist;
- a historical JMD order keeping its amount and currency when the catalog is re-seeded in USD.

## Currency of historical records

- **No conversion is performed, and none is needed.** Orders, payments, refunds and disputes keep the amount and currency they were recorded in. The development database still holds 8 orders and 2 cash records in Jamaican dollars with their original amounts. `Money` never adds amounts of different currencies; reports show each currency separately.
- **Events** moved to USD when the sample catalog was rebuilt. Their old orders keep JMD, and their new prices are clearly defined USD sample values.
- **A risk in `000020`:** that migration falls back to the default currency for a row with a blank or unknown currency code. Before it, currency columns defaulted to JMD, so no such row existed, and none exists now. It has already run, so it is left unchanged. Any future data migration must reject or flag unknown currencies, never default them.
- **If a conversion is ever genuinely required,** for example to restate history in one reporting currency:
  1. never overwrite the original amount and currency;
  2. record the converted figure next to the original, with the rate, its source, the date and the person approving;
  3. write an `order_adjustments` or audit-log entry for every converted record;
  4. run it as an explicit, reviewed command, never inside a schema migration or seeder.

## Uncommitted migrations in the working copy (not part of this change)

Migrations `000021`–`000030` are uncommitted. `000021` belongs to the unfinished payment-processor work; `000022`–`000030` come from separate work by another tool. They are not on the committed path and were not changed. Problems found:

1. `000030` adds `system_platform_settings.payment_processors`, which `000021` already adds. On a fresh database it fails with "duplicate column", which is why the working copy's test suite fails at setup.
2. Duplicate models:
   - `host_payment_settings` (`000029`) duplicates `host_payment_policies`, which `000021` extends with the same enable switches.
   - `payment_configurations` (`000027`) duplicates `system_platform_settings.payment_processors`.
   - The platform fee and tax columns in `000030` duplicate `charge_rules`.
   - `cuisine_categories` (`000023`) and `menu_items.cuisine` overlap `config/traditions.php` and `events.traditions`.
   - `events.payment_methods` (`000026`) overlaps the organizer's payment switches.
3. These should be consolidated onto the existing tables before they are committed.
