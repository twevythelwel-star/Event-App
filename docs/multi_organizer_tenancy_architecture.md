# Multi-Organizer Tenancy & Role Boundaries Architecture

> **Document Classification:** Production Architectural Specification & Tenancy Blueprint  
> **Status:** Implemented & Verified  
> **Target Platform:** EventBite (Laravel 11 Backend + React 18 TypeScript Frontend)
> **Tenancy Model:** Single-Database Row-Level Tenant Scoping with Multi-Layer Defense-in-Depth  

---

## 1. Executive Summary & Design Philosophy

The EventBite platform was initially conceived to support the owner's premier community events (such as the annual **Sunday Fish Fry** in Kingston, Jamaica). However, to scale commercially across Jamaica, the wider Caribbean, and US diaspora hubs, the system has been architected so **hundreds of independent event organizers can operate autonomously on the platform without rebuilding or refactoring the core application**.

---

## 2. Why Single-Database Scoped Tenancy (Instead of Multi-Database)?

We deliberately opted **against** a multi-database architecture (database-per-tenant) for launch. Complex multi-database systems introduce severe operational friction:

| Comparison Dimension | Single-Database Row Scoped (Chosen) | Multi-Database Tenant System (Rejected) |
| :--- | :--- | :--- |
| **Database Migrations** | **Instant (< 2 seconds):** Runs standard `php artisan migrate` once across all tables. | **Fragile & Slow:** Must loop over $N$ databases; connection failures leave tenants on desynchronized schemas. |
| **Connection Pooling** | **1 Efficient Shared Pool:** Scalable connection pooling with PgBouncer or MySQL pool. | **Pool Starvation:** $N$ connection pools rapidly exhaust server memory and open file limits. |
| **Platform Analytics** | **Frictionless SQL Aggregations:** Direct queries for platform-wide gross merchandise volume and AML risk. | **Requires Distributed Joins / ETL:** Cannot aggregate without complex foreign data wrappers or pipeline lag. |
| **Tenant Provisioning** | **Sub-second (`INSERT INTO organizers`):** Immediate self-serve onboarding. | **Slow (15-60 seconds):** Must provision database instance, seed schema, and grant user permissions. |
| **Operational Cost** | **Minimal:** Low-cost hosting on single managed RDS or Aurora instance. | **Cost Bloat:** Minimum instance sizing multiplied across inactive or low-volume organizers. |

---

## 3. The 5-Layer Defense-in-Depth Isolation Model

To guarantee that a single database never leaks records between organizers, we enforce **five concentric security boundaries**:

1. **Layer 1: HTTP Middleware (`EnsureTenantAccess.php`):** Resolves tenant from request header `X-Organizer-ID`, route parameter `slug`, or host subdomain (`kingstonyouth.events.app`); validates active membership in `organizer_users`.
2. **Layer 2: Eloquent Global Scope (`TenantScope.php`):** Intercepts all Eloquent queries (`SELECT`, `UPDATE`, `DELETE`) on tenant models, automatically injecting `WHERE organizer_id = ?`.
3. **Layer 3: Model Trait (`BelongsToTenant.php`):** Automatically injects `organizer_id = TenantContext::id()` on record creation.
4. **Layer 4: Laravel Authorization Policies (`EventPolicy`, `OrderPolicy`, `PaymentPolicy`, `TicketPolicy`, `StaffPolicy`, `ReportPolicy`):** Enforces strict permission checks on every mutation and view.
5. **Layer 5: PostgreSQL Row-Level Security (RLS) & S3 Prefixing:** Database engine enforcement and media namespace isolation (`/tenants/{organizer_id}/flyers/`).

---

## 4. Role & Ownership Boundary Matrix (RBAC)

Four distinct roles have been established with rigid boundaries:

| Capability / Entity | Platform Administrator | Event Owner (Organizer Admin) | Payment Verifier (Cashier) | Food Collection Staff (Kitchen) |
| :--- | :---: | :---: | :---: | :---: |
| **System Identity** | `platform_admin` | `event_owner` | `payment_verifier` | `food_collector` |
| **Organizer Lifecycle** | **Full CRUD** (Provision / Suspend) | Read Own Tenant Profile | Read Own Tenant Profile | Read Own Tenant Profile |
| **Events & Flyers** | Global Oversight | **Full CRUD** | Read Only | Read Only |
| **Menu Dishes & Pricing** | View / Support | **Full CRUD** | **Locked (Read-Only)** | **Locked (Read-Only)** |
| **Customer Contact & PII** | Auditable Trail | **Full Unmasked View** | **Full Unmasked View** | **Masked (Phone Redacted)** |
| **Order Payment Status** | SuperAdmin Override | **Verify / Reject / Refund** | **Verify / Reject / Partial** | **Locked (Read-Only)** |
| **Cash In-Person Receipts** | Auditable Trail | **Record Receipt** | **Record Receipt** | **Forbidden** |
| **Cash Ledger Reversals** | SuperAdmin Override | **Authorized** | **Forbidden (Needs Owner)** | **Forbidden** |
| **Bank / Zelle Settings** | Platform Infrastructure | **Configure Credentials** | **Locked (Read-Only)** | **Locked (Read-Only)** |
| **Ticket QR Scanning** | Global Inspection | **Scan & Override** | **Scan Tickets** | **Scan Tickets & Portions** |
| **Meal Portion Handover** | Inspection | **Record Handover** | **Record Handover** | **Primary Duty** |
| **Financial Revenue Reports** | Global Aggregation | **Full Access (Own Tenant)** | **Forbidden (Locked)** | **Forbidden (Locked)** |
| **Kitchen Portion Prep Sheet** | View | **Full Access** | **Full Access** | **Full Access (Primary View)** |

---

## 5. Tenancy Evolution Roadmap

1. **Stage 1: Launch Architecture (Current - 0 to 1,000 Organizers):** Single PostgreSQL database, `organizer_id` UUID column with composite B-tree indexes, Eloquent `TenantScope`, and Laravel policies.
2. **Stage 2: Mid-Tier Enterprise Expansion (1,000 to 10,000 Organizers):** PostgreSQL Schema-per-tenant (`tenant_{slug}.*`) on shared cluster for isolated schema backups and independent autovacuum.
3. **Stage 3: High-Compliance Sovereign Tier (Special Enterprise Contracts Only):** Dedicated Database-per-tenant for physical data residency mandates and customer-managed encryption keys (AWS KMS).
