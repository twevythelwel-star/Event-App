# Island Events Platform: Production Deployment & Operations Architecture

Rewritten 2026-10-30 to match what the code actually contains. Earlier versions described PostgreSQL with
pgBackRest, Redis with Laravel Horizon, `spatie/laravel-backup`, scheduled commands, JSON logging and Sentry.
None of these were installed or tested. Each section below says plainly whether something is **in place and
verified**, **in place but not verified in production**, or **not in place (recommended)**.

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

The host event form writes flyers through the `s3_flyers` disk. Despite its name, that disk is a local disk on the
same private root, so the flyer route finds the file.

Production requirements:
- `storage/` must be **persistent and shared across releases**. See the `shared/storage` link in §9. On a multi-server
  or ephemeral-container host, mount a persistent volume there.
- Back up `storage/app/private` together with the database (§6).

**Not in place:** S3 or R2 object storage. To move to it, install `league/flysystem-aws-s3-v3`, switch the
`s3_flyers` disk and `MediaStorageService::DISK` to an S3 disk at the same time, and copy the existing
`tenants/` tree across.

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

**Current state:** `routes/console.php` schedules nothing. Event reminders are sent on demand by the host
(`POST /api/v1/operations/events/{eventId}/reminders`). Ordering cut-offs are checked when an order is placed, not by
a background task.

Install the standard cron line anyway, so scheduled tasks start working as soon as any are added:
```bash
# crontab -u www-data -e
* * * * * cd /var/www/eventbite/current && php artisan schedule:run >> /dev/null 2>&1
```

**Recommended next:** schedule `queue:prune-failed --hours=168` daily, and add automatic event reminders if hosts
shouldn't have to send them by hand.

---

## 6. Backups and restore

**Not in place in the application.** There is no backup package. Use one of these and test it (see the drill
below).

**Option A (preferred): managed MySQL backups.** On a managed database (AWS RDS, DigitalOcean, Laravel Cloud and
similar), turn on daily automated snapshots with at least 14 days' retention, plus binary-log point-in-time recovery.

**Option B: scheduled dump**, on a self-managed server:
```bash
#!/usr/bin/env bash
# /usr/local/bin/eventbite-backup.sh  (cron: 0 2 * * *)
set -euo pipefail
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
DEST=/var/backups/eventbite/$STAMP
mkdir -p "$DEST"
mysqldump --single-transaction --routines --triggers --set-gtid-purged=OFF \
  --defaults-extra-file=/etc/eventbite/mysql-backup.cnf eventbite | gzip > "$DEST/db.sql.gz"
tar -czf "$DEST/private-storage.tar.gz" -C /var/www/eventbite/shared/storage/app private
# Copy $DEST off the server (object storage with versioning or object lock), then prune local copies older than 7 days.
find /var/backups/eventbite -mindepth 1 -maxdepth 1 -mtime +7 -exec rm -rf {} +
```
`/etc/eventbite/mysql-backup.cnf` (mode 600) holds the backup user's credentials, so no password appears on the
command line.

**Restore:**
```bash
gunzip -c db.sql.gz | mysql --defaults-extra-file=/etc/eventbite/mysql-restore.cnf eventbite_restore
tar -xzf private-storage.tar.gz -C /var/www/eventbite/shared/storage/app
```

**Restore drill (monthly):** restore the latest backup into a scratch database, then point a copy of the app at it
with `APP_ENV=staging`. Check that the event, order and audit counts match production for the same moment, and that
a stored flyer and a QR artwork load. Record the time the drill took; that is your real recovery time.

---

## 7. HTTPS and security headers

The app expects HTTPS in production. Required settings:
- `APP_ENV=production`, `APP_DEBUG=false`, and `APP_URL=https://your-domain`. `APP_URL` is also the base of every
  event QR code (`/e/{code}`), so set it before the first event is created.
- `SESSION_SECURE_COOKIE=true` (its default is unset). `SESSION_HTTP_ONLY` and `SESSION_SAME_SITE=lax` are already
  the defaults.

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

- **Logging (in place):** Laravel's default `stack` channel writing `storage/logs/laravel.log`. In production set
  `LOG_STACK=daily`, `LOG_LEVEL=info` and `LOG_DAILY_DAYS=14`. **Not in place:** structured JSON logging and
  automatic PII redaction, so don't ship logs to a third party without adding redaction first.
- **Audit trail (in place):** security-relevant actions are written to `security_audit_logs`, and food handovers to
  `handover_audit_logs`. These include payment decisions, refunds, cash, check-ins and admin changes.
- **Error monitoring (not in place):** there is no Sentry or Bugsnag. Recommended before launch: install
  `sentry/sentry-laravel`, set `SENTRY_LARAVEL_DSN`, and alert on new errors.
- **Secrets (in place):** `backend/.env` is in `.gitignore`. Keep production secrets in the host's secret store
  or a mode-600 `.env` in `shared/`. `php artisan env:encrypt` and `env:decrypt` are available if you deploy an
  encrypted file. These values must be set: `APP_KEY`, `APP_URL`, the `DB_*` values, `SESSION_SECURE_COOKIE`,
  `MAIL_*`, and `TWILIO_*` (for SMS).
- **Note:** `backend/.env.example` currently contains a realistic-looking Twilio account identifier. Replace it
  with a placeholder.

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
rm -rf storage && ln -s "$APP/shared/storage" storage

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
| Scheduler | Nothing scheduled | — |
| Backups and restore | Not in place in the app | Use §6 and run the restore drill |
| HTTPS, secure cookies | Configuration required | §7 |
| Error monitoring | Not in place | §8 |
| Structured logging and PII redaction | Not in place | §8 |
