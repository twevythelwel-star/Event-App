# EventBite Platform: Production Deployment & Operations Architecture

Rewritten 2026-10-30 to match what the code actually contains. Earlier versions described PostgreSQL with
pgBackRest, Redis with Laravel Horizon, `spatie/laravel-backup`, scheduled commands, JSON logging and Sentry.
None of these were installed or tested. Each section below says plainly whether something is **in place and
verified**, **in place but not verified in production**, or **not in place (recommended)**.

For Laravel Cloud specifically (ephemeral filesystem, managed queues, buckets, scheduler toggle) see
`laravel_cloud_deployment.md`.

---

## 1. Platform baseline

| Component | Version / choice | Status |
| :--- | :--- | :--- |
| PHP | 8.4 (composer allows ^8.3); extensions `mbstring`, `pdo_mysql`, `intl`, `gd`, `fileinfo` | CI runs 8.4 |
| Laravel | 13.x (`laravel/framework` ^13.17) | CI |
| Database | **MySQL 8.0**, `utf8mb4` | CI runs every migration, the seeders and the full test suite on MySQL 8 (`backend-mysql` job) |
| Web server | NGINX + PHP-FPM, or a managed Laravel host (Laravel Cloud / Forge) | Not verified in production |
| Frontend | Blade views served by Laravel. The React SPA in `src/` is a development/demo client; it is built in CI but not deployed | CI |

### Why MySQL and not PostgreSQL
`id` columns created with `$table->uuid()` are `char(36)` on MySQL but a strict `uuid` type on PostgreSQL. The app
stores identifiers that are not UUIDs in some of them. Events created by the host API get `evt-` + 10 characters
(`EventManagementService::createEvent`), and demo data uses keys such as `evt-kingston-harbour-fry` and
`org-kingston-youth`. PostgreSQL rejects those values, so host event creation would fail there. Moving to
PostgreSQL first needs those IDs changed to real UUIDs, plus a CI job on PostgreSQL.

---

## 2. Database

- **Connection:** `DB_CONNECTION=mysql` plus `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`.
  Use a database user limited to this schema.
- **Migrations:** they are additive (expand/contract). Run them during deployment with:
  ```bash
  php artisan migrate --force --isolated
  ```
  `--isolated` takes a cache lock, so two servers deploying at once can't migrate concurrently. It relies on the
  `cache_locks` table, which exists because `CACHE_STORE=database`.
- **Expand and contract:** add new columns as nullable or with a default, and deploy code that handles both
  shapes. Remove old columns only in a later release. Never edit a migration that has already run in production.
- **Demo data:** `php artisan db:seed` loads demo accounts with a shared, well-known password. **Never run the
  seeders in production.** As a safeguard, each demo seeder does nothing when `APP_ENV=production`.

---

## 3. File storage

**In place:** uploaded flyers, menu photos and event QR artwork are stored on the **private local disk**
(`storage/app/private`, the `local` disk). The app serves them through its own routes, which check whether the
viewer may see them:
- `api.media.flyer` serves flyers.
- The menu photo route serves menu photos.
- `/events/{id}/qr/...` serves QR artwork to hosts, and `/e/{code}/qr.svg` serves it publicly for published
  events.

All uploads — flyers, menu photos, event QR artwork, host ID documents — go through `MediaStorageService`
(or the host-verification disk config) on the disk named by `MEDIA_DISK` / `HOST_ID_STORAGE_DISK`, default
`local` (the private root above). `league/flysystem-aws-s3-v3` is installed, so setting `MEDIA_DISK=s3` (with
the `AWS_*` values, or the credentials a host injects) moves every upload to an S3-compatible bucket —
required on hosts with an ephemeral filesystem such as Laravel Cloud (see `laravel_cloud_deployment.md`).
Every file is still served through the app's authorized routes; buckets stay private.

Production requirements:
- `storage/` must be **persistent and shared across releases**. See the `shared/storage` link in §9. On a multi-server
  or ephemeral-container host, mount a persistent volume there.
- Set `PRIVATE_MEDIA_ROOT=/var/www/eventbite/shared/storage/app/private` so media paths stay stable across releases.
- Back up `storage/app/private` together with the database (§6).

**Optional:** S3 or R2 object storage is supported (`MEDIA_DISK=s3`, `HOST_ID_STORAGE_DISK=s3`) for hosts with
ephemeral filesystems; see §3 and `laravel_cloud_deployment.md`. To move an existing local tree to a bucket,
upload the `tenants/` and `host-identity-documents/` prefixes, then switch the disk settings.

---

## 4. Queue worker

**In place:** `QUEUE_CONNECTION=database`, using the `jobs` and `failed_jobs` tables. Notification jobs (SMS and
email through `SendNotificationJob` and the `App\Notifications\*` classes) are queued. The notification classes use
the `notifications` queue, so the worker must listen to it **as well as** `default`:

```ini
; /etc/supervisor/conf.d/eventbite-worker.conf
[program:eventbite-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/eventbite/current/artisan queue:work database --queue=notifications,default --tries=3 --backoff=30 --max-time=3600 --sleep=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/eventbite/shared/storage/logs/worker.log
stopwaitsecs=3600
```

- After each deployment, run `php artisan queue:restart` so workers load the new code.
- Failed jobs: list them with `php artisan queue:failed`, retry with `php artisan queue:retry all`, and set up an
  alert when the table grows.

**Not in place (optional later):** Redis and Laravel Horizon. Switch only if the database queue becomes a
bottleneck. That means installing `laravel/horizon` and `predis/predis` and providing Redis.

---

## 5. Scheduler

**Current state:** Laravel schedules queue-failure pruning daily, unpaid inventory reservation expiry every minute,
and Stripe payment reconciliation every five minutes. Event reminders remain on demand by the host
(`POST /api/v1/operations/events/{eventId}/reminders`). Run the standard cron line so these schedules execute:
```bash
# crontab -u www-data -e
* * * * * cd /var/www/eventbite/current && php artisan schedule:run >> /dev/null 2>&1
```

The reservation expiry command is `php artisan commerce:expire-reservations --batch=200`. It locks each order and
event/menu inventory rows, restores tracked stock only once, marks the unpaid order expired, and writes an audit event.
The default hold is 15 minutes (`COMMERCE_RESERVATION_MINUTES`). A provider attempt that is pending, processing, or
already succeeded is held for reconciliation rather than having its stock released. Partially paid/manual exceptions
require an authorized refund/cancellation decision; the expiry job does not discard a received payment.

The provider reconciliation command is `php artisan payments:reconcile-stripe --limit=100 --stale-minutes=5`. It
queries Stripe PaymentIntents directly, compares the provider ID, amount, currency, EventBite order metadata, received
amount and available refund total, and applies a delayed successful settlement only when all values match. It records
matched/mismatch/error state and alerts on mismatches. The provider checkout/webhook integration is still disabled;
this command does not make card checkout available. A future integration must create intents with
`metadata[eventbite_order_id]` and internal `Payment` rows before reconciliation can match them. Configure
`STRIPE_SECRET_KEY` through the deployment secret store, then verify the command against Stripe test mode before
enabling any customer payment method.

### Event-day connectivity and safe retry

The Laravel worker terminal validates every admission online against the order row under a database lock. If its
request loses connectivity, it preserves the code for retry and displays **NOT ADMITTED**. Each retry reuses a
random request ID; if the server committed admission but its response was lost, the same authorized worker receives
the original successful verdict. A different request or scanner is still rejected as a duplicate. The terminal does
not authorize admission while offline. This fail-closed behavior avoids duplicate admissions across disconnected
devices; staff should use the ticket QR scanner and supervisor-authorized manual lookup when the service is reachable.
No offline admission cache or synchronization protocol is enabled.

---

## 6. Backups and restore

**Repository tooling added:** `scripts/production-backup.sh` creates a MySQL dump and private media archive,
verifies archive integrity, creates SHA-256 checksums, supports GPG recipient encryption, retention cleanup,
and an operator-supplied offsite copy command. Configure a host secret-backed defaults file and offsite storage;
do not put credentials or encryption keys in the repository. Schedule it daily (02:00 UTC), retain 30 days, and
monitor successful offsite copies. Daily backups provide a target RPO <= 24 hours only when the offsite copy
completes; enable managed MySQL binary-log/PITR for a lower RPO. Target RTO <= 4 hours pending measured drills.

**Option A (preferred): managed MySQL backups.** On a managed database (AWS RDS, DigitalOcean, Laravel Cloud and
similar), turn on daily automated snapshots with at least 14 days' retention, plus binary-log point-in-time recovery.

**Option B: scheduled dump**, on a self-managed server. Configure offsite storage and encryption, then schedule the added script:
```cron
0 2 * * * APP_ROOT=/var/www/eventbite BACKUP_ROOT=/var/backups/eventbite MYSQL_DATABASE=eventbite RETENTION_DAYS=30 MYSQL_DEFAULTS_FILE=/etc/eventbite/mysql-backup.cnf BACKUP_GPG_RECIPIENT=backup@example.invalid BACKUP_OFFSITE_COMMAND='rclone copy "$1" remote:eventbite-backups/' bash /var/www/eventbite/current/scripts/production-backup.sh
```

The cron command above is the supported self-managed backup entry point; do not substitute an unverified dump
snippet. `/etc/eventbite/mysql-backup.cnf` (mode 600) holds the backup user's credentials, so no password appears
on the command line. Verify the offsite command exits successfully and alert on missing daily backup directories.

**Restore:**
```bash
BACKUP_GPG_PRIVATE_KEY_FILE=/etc/eventbite/backup-private-key.asc \
MYSQL_DEFAULTS_FILE=/etc/eventbite/mysql-restore.cnf \
bash /var/www/eventbite/current/scripts/production-restore-drill.sh \
  /var/backups/eventbite/20261101T020000Z eventbite_restore_drill_20261101 /tmp/eventbite-restore-drill-20261101
```

**Restore drill (monthly):** restore only into a disposable database named `eventbite_restore_*` and a `/tmp/eventbite-restore-*`
media directory. The script verifies checksums, MySQL load, table counts and media files. Then point a staging app at
the restored DB/media and verify private flyer/menu media through authorized routes, ticket lookup, login, and a queue
operation. Record start/end time, counts, checksums and media verification. Until the staging drill is executed and
recorded, backups are unverified and RTO is only a target.

---

## 7. HTTPS and security headers

The app expects HTTPS in production. Required settings:
- `APP_ENV=production`, `APP_DEBUG=false`, and `APP_URL=https://your-domain`. `APP_URL` is also the base of every
  event QR code (`/e/{code}`), so set it before the first event is created.
- `SESSION_SECURE_COOKIE=true`, `DB_CONNECTION=mysql`, production database credentials, `PRIVATE_MEDIA_ROOT` on
  durable storage, a real mail provider (`MAIL_MAILER`), `QUEUE_CONNECTION=database`, and a durable cache store.
  Laravel startup now fails in production when debug, URL, secure cookie, database, mail, queue/cache or media-root
  configuration is unsafe. `SESSION_HTTP_ONLY` and `SESSION_SAME_SITE=lax` remain secure defaults.
- `TRUSTED_PROXIES=127.0.0.1` when NGINX on the same server proxies to PHP, so the app trusts only that proxy's
  `X-Forwarded-*` headers (the default `*` suits a platform load balancer such as Laravel Cloud's).
- The app sets its own security headers (`App\Http\Middleware\SecurityHeaders`): HSTS for one year on HTTPS
  responses (`SECURITY_HSTS_MAX_AGE`), `nosniff`, `SAMEORIGIN` framing, a strict referrer policy and a
  permissions policy. NGINX may repeat them; a header the response already has is not changed by the app.
- Notifications queue on `notifications` (`NOTIFICATIONS_QUEUE`); the worker below listens on it and on `default`.

Example NGINX origin configuration (replace the domain):
```nginx
server {
    listen 80;
    server_name example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name example.com;
    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    root /var/www/eventbite/current/backend/public;
    index index.php;
    client_max_body_size 8M; # flyer uploads are capped at 5 MB by validation

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 8. Logging, error monitoring and secrets

- **Logging and critical alerts (implemented, target unverified):** configure `LOG_CHANNEL=stack` with
  `LOG_STACK=daily,slack`, `LOG_SLACK_WEBHOOK_URL` (HTTPS secret), `LOG_LEVEL=info`, `LOG_SLACK_LEVEL=error`,
  and `LOG_DAILY_DAYS=14`. Detailed exceptions stay in retained local logs; curated exception, queue and
  payment failure alerts go to Slack. Startup refuses production unless the stack contains both `daily` and
  `slack` with an HTTPS webhook.
  Failed queue jobs, payment failures/transitions, slow requests, hashed failed sign-ins, and ticket redemption
  conflicts are logged. Do not include customer payment data or secrets in alert payloads.
- **PII and secret redaction (in place):** `App\Logging\RedactSensitiveLogData` is tapped onto the `single`,
  `daily`, `slack` and `stderr` channels. It replaces credential-, token-, card- and identity-document-shaped
  keys and values with `[redacted]` before a record is written or alerted.
- **Audit trail (in place):** security-relevant actions are written to `security_audit_logs`, and food handovers to
  `handover_audit_logs`. These include payment decisions, refunds, cash, check-ins and admin changes.
- **Error monitoring:** Slack critical/error alerts are configured as above. Sentry is not installed; it remains
  recommended for exception grouping and performance traces.
- **Secrets (in place):** `backend/.env` is in `.gitignore`. Keep production secrets in the host's secret store
  or a mode-600 `.env` in `shared/`. `php artisan env:encrypt` and `env:decrypt` are available if you deploy an
  encrypted file. These values must be set: `APP_KEY`, `APP_URL`, the `DB_*` values, `SESSION_SECURE_COOKIE`,
  `MAIL_*`, `LOG_SLACK_WEBHOOK_URL`, and `TWILIO_*` when SMS notifications are offered.
- **Notification providers:** SMS is disabled unless real Twilio account credentials and sender configuration are
  supplied. Verify a delivery and signed callback in staging before advertising SMS to customers.

---

## 9. Deployment and rollback

```
/var/www/eventbite/
├── current -> releases/20261128_104500
├── shared/
│   ├── .env
│   └── storage/            (persistent: app/private holds flyers, photos, QR artwork)
└── releases/
```

```bash
#!/usr/bin/env bash
# deploy.sh
set -euo pipefail
APP=/var/www/eventbite
RELEASE="$APP/releases/$(date +%Y%m%d_%H%M%S)"

git clone --depth 1 -b main git@github.com:twevythelwel-star/Event-App.git "$RELEASE"
cd "$RELEASE/backend"
ln -s "$APP/shared/.env" .env
ln -sfn "$APP/shared/storage" storage

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan migrate --force --isolated

ln -sfn "$RELEASE" "$APP/current"
sudo systemctl reload php8.4-fpm
php artisan queue:restart
```

**Rollback:** point `current` back at the previous release, reload PHP-FPM and run `php artisan queue:restart`.
Because migrations are additive (expand/contract), the previous release keeps working with the newer schema, so
**don't run `migrate:rollback` in production** unless a specific migration's down step has been reviewed and
tested. Restore from backup (§6) instead if data was damaged.

---

## 10. Verification status

| Area | Status | How it is verified |
| :--- | :--- | :--- |
| Migrations + seeders on MySQL 8 | In place and verified | CI `backend-mysql`: `migrate:fresh --seed` |
| Full test suite on MySQL 8 | In place and verified | CI `backend-mysql`: `php artisan test` |
| Full test suite on SQLite, standalone checks, PHP lint | In place and verified | CI `backend` |
| File storage (private local disk) | In place and verified | Feature tests (`FlyerStorageTest`, media and QR tests). Persistence across releases depends on the host |
| Queue (database driver) | In place, not verified in production | Worker command in §4 |
| Scheduler | In place (retention purge, reservation expiry, Stripe reconciliation) | §5 |
| Backups and restore | Scripts supplied; no staging restore has been run | §6 |
| HTTPS, secure cookies | Required and app startup guarded; host TLS not verified | §7 |
| Error monitoring | Slack alerts in place; Sentry not installed | §8 |
| Structured logging and PII redaction | In place (`RedactSensitiveLogData` on all file/alert channels) | §8 |
| S3-compatible object storage | Supported (`MEDIA_DISK=s3`, `HOST_ID_STORAGE_DISK=s3`); required on ephemeral hosts | §3, `laravel_cloud_deployment.md` |
| Production configuration guard | In code; actual host variables still need validation | `AppServiceProvider` startup checks |
| Ticket QR vs event QR | Separate IDs and routes; event QR cannot admit | `EventQrCodeTest`, `TicketCheckInTest` |
| Mobile camera scan | Not verified on physical devices | Staging/device check required |
