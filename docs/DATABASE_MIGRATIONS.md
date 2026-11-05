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

## Consolidation of the uncommitted migrations (2 November 2026)

The working copy held uncommitted migrations from the unfinished payment work (`000021`) and from another tool (`000022`–`000030`), several of which duplicated existing tables. They were consolidated onto the existing schema:

| Was | Now |
|---|---|
| `host_payment_settings` table and `HostPaymentSetting` model (`000029`) | Removed. The organizer's payment switches live on `host_payment_policies` (columns added by `000021`). `HostPaymentPolicy` lists them, and the host payment-settings API reads and writes that row, for organizer owners only. |
| `payment_configurations` table and model (`000027`), storing Stripe and PayPal secret keys as plain JSON | Removed. Credentials come only from the environment (`config/payments.php`). Admins approve or withdraw a processor on `system_platform_settings.payment_processors` (`000021`), which is audited. The routes that saved credentials are gone. |
| A second `payment_processors` column (`000030`) | Removed. This was the "duplicate column" that stopped every test. |
| Platform fee and tax columns (`000030`) and their admin controllers | Removed. Fees and taxes are the existing `charge_rules` (Admin → Fees & Taxes). |
| Host approval columns (`000030`) and `HostApprovalController`, which used organizer columns that never existed | Removed. Hosts are approved through `host_applications`. |
| `cuisine_categories` table, model, controller and seeder (`000023`) | Removed. `menu_items.cuisine` holds a tradition key from `config/traditions.php`, the list hosts use for events, and is validated against it. |
| An empty migration (`000028`) | Removed |

The remaining new columns stay, because they duplicate nothing:
- **Menu items:** dietary tags, allergens, spice level, portion sizes, vendor and preparation notes (now `000034`).
- **Order items:** vendor (`000035`).
- **Orders:** ticket restrictions (`000036`).
- **Events:** the subset of the organizer's payment methods an event offers (`000037`).
- **Platform settings (`000038`):** QR rules, notification channels, fraud limits and feature flags.

These migrations were renamed to `000034`–`000038` so they run after the committed `000033`; none had run on any database. They, and the consolidated controllers, models and routes, are still uncommitted: they build on the unfinished payment work (`000021`), which is not committed either. Only `000043` and this document are committed so far.

Later the same tool edited the committed, already-run `000033` to add four indexes, one of them on a non-existent `order_charges.type` column. SQLite accepted that silently; MySQL would not, so a fresh MySQL install would have failed. `000033` is restored to its committed version, and the four indexes are added forward-only by `000043_add_order_history_indexes`, using the real column `kind`. The tool also added a currency pre-check to the committed `000020`. That only affects fresh installs, and its own test covers it.

**Removed for good:** the twelve files of the removed duplicates (four migrations, three models, four controllers, one seeder) were never committed and could not be backed up before removal.

## Event studio data: what exists and where each rule is enforced (4 November 2026)

The schema was inspected before anything was added. Each suggested entity is either already a table, or already held by an existing table; no duplicate tables or models were created.

| Suggested entity | Where it lives |
|---|---|
| event_cultures, cultural_categories, cultural_regions | `cultural_categories`, `cultural_regions`, `cultural_region_countries` (000044) |
| event_countries, cuisines, dishes, event_dishes | `event_countries`, `cuisines`, `dishes`, `event_dishes` (000044) |
| event_menu_items, event_menu_categories | `menu_items` (`event_id`, `category` is the menu section and food station) |
| menu_item_images | `menu_items.image_path` (private disk, re-encoded on upload), `image_position`, `image_is_illustrative` |
| meal_packages, meal_package_items | `menu_items` with `item_type = 'package'` and `package_config` (components, choices, extras) (000045) |
| menu_item_options, menu_item_option_values | `menu_items.custom_option_groups` (required/optional, max choices, upgrade prices) |
| event_ticket_designs | each ticket type in `events.ticket_tiers` (`designStyle`, `accentColor`, `backgroundStyle`) |
| event_gallery_images | `event_media` (000046, 000047) |
| event_preview_settings | not stored: previews render the live event (`events.preview`, the menu studio's Attendee Preview) |

Migration `000049` adds `events.creation_key` (unique per organizer) and, on MySQL, CHECK constraints for menu stock and price and order-line preparation status.

| Rule | Enforced by |
|---|---|
| Hosts manage only their organizers' events | `EventManagementService::findEvent` (every studio, menu, review and publish action) |
| Menu items belong to their event | items are created and found by `event_id`; checkout locks rows `where event_id = …` |
| Packages use only the same event's items | `MealPackage::normalizeConfig` on save; `MealPackage::price` resolves parts only from the event's own menu, so an altered package cannot pull in another event's items; an item a package uses cannot be deleted |
| Ticket and item stock never negative | event row and item rows locked `FOR UPDATE` during checkout; MySQL CHECK on `menu_items.available_stock` |
| Money in minor units with explicit currency | `price_minor` + `currency` on items; orders and lines keep their own currency; history is never converted |
| One event per create form | `creation_key` (form and autosave); a retry returns the existing event; the unique index stops a race |
| Drafts never purchasable | checkout 404s for unpublished events; `OrderPlacementService` refuses any order for a non-published event |
| Publishing | `EventManagementService::publicationReadiness`: name, description, date and time, location, contact, tickets and inventory, food menu when food ordering is on, refund policy, permission. Critical items block `setStatus('published')` and every publish button. |
