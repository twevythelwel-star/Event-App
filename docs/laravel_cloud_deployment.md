# EventBite on Laravel Cloud (MySQL 8)

How to prepare and verify this application for Laravel Cloud. The generic self-managed deployment is in
`production_deployment_and_operations.md`; this page covers only what differs on Cloud. Facts about Cloud come
from its documentation (links at the end), checked on 2026-11-09.

## What Cloud changes

| Concern | Self-managed | Laravel Cloud |
| :--- | :--- | :--- |
| Filesystem | Persistent `storage/` via releases symlink | **Ephemeral** — reset on every deploy; each replica has its own disk |
| Uploaded media | Private local disk (`MEDIA_DISK=local`) | **Object storage bucket required** (a private Laravel Object Storage bucket) |
| Queue | `queue:work` under Supervisor | Managed queue (one queue name per managed queue) |
| Scheduler | cron `schedule:run` | Scheduler toggle on the App cluster |
| HTTPS/TLS | NGINX + certbot | Terminated by Cloud's proxy; the app trusts its `X-Forwarded-*` headers |
| Security headers | NGINX | `App\Http\Middleware\SecurityHeaders` (the app sets them itself) |
| Database backups | `scripts/production-backup.sh` | Laravel MySQL automatic daily backups (1–30 days retention) + manual backups |
| Media backups | in `production-backup.sh` | **Not provided by Cloud** — see "Outstanding" |

## Required packages

`league/flysystem-aws-s3-v3` (buckets) and `aws/aws-sdk-php` (managed queues) are in `composer.json`. Managed
queues need Laravel 13.19.0 or later; the app is on 13.38.

## Environment variables

Cloud writes environment variables to `.env` before the app starts. It injects `DB_*` when a database is attached,
`QUEUE_CONNECTION=cloud` when a managed queue exists, and configures the bucket's filesystem disk under the disk
name you choose when attaching a bucket. Your own variables override injected ones. Set these yourself:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app.laravel.cloud        # also the base of every event QR code
APP_KEY=base64:...                            # php artisan key:generate --show

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true                    # startup guard requires it
CACHE_STORE=database                          # or the Valkey store Cloud sets when you attach a cache

LOG_CHANNEL=stack
LOG_STACK=daily,slack
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/...   # startup guard requires an HTTPS webhook
LOG_LEVEL=info
LOG_DAILY_DAYS=14

MEDIA_DISK=media                              # the disk name you gave the bucket when attaching it
HOST_ID_STORAGE_DISK=media                    # ID documents (encrypted at rest) also off the ephemeral disk
NOTIFICATIONS_QUEUE=notifications             # empty on a plan with one managed queue (see Compute setup)

MAIL_MAILER=...                               # a real provider; 'log'/'array' fail the startup guard
LEGAL_COUNSEL_REVIEWED=false                  # set true + LEGAL_COUNSEL_REVIEWED_ON only after counsel sign-off
```

Leave `TRUSTED_PROXIES` unset (`*`): Cloud's proxy terminates TLS and sends `X-Forwarded-Proto`, and the app
trusts it so HTTPS, secure cookies and visitor IPs are correct. The bucket must be **private** (Cloud buckets have
one visibility for every file); files are served only through the app's authorized media and document routes.

## Compute setup

1. **App cluster** — PHP 8.4. Build commands: `composer install --no-dev` and `php artisan optimize`. Deploy
   command: `php artisan migrate --force`. Do **not** run `storage:link`, `optimize:clear` or `queue:restart`
   (Cloud handles them). The startup guard does not run during the build, so the build never needs secrets.
2. **Managed queues** — Add compute → Managed queue. Each managed queue serves one queue name; the first one is
   the default and takes every job dispatched without a queue name. Customer and host messages are queued on
   `notifications`, so either:
   * create a second managed queue named `notifications` (Growth plan and above), or
   * on a plan with one managed queue (Starter), set `NOTIFICATIONS_QUEUE=` (empty) so messages use the default.
   Failed jobs are handled in the Queues dashboard; `queue:failed`/`queue:retry` do not work with managed queues.
3. **Scheduler** — enable the Scheduler toggle. Every scheduled task is `onOneServer()` and
   `withoutOverlapping()`, so replicas never run a task twice (locks use the database cache).

## Checklist

Status of each item. "Verified" means checked in this repository; "On first deploy" items need a real Cloud
environment.

| Item | Status | How |
| :--- | :--- | :--- |
| Migration integrity | **Partly verified** | All 66 migrations pass on SQLite (full suite). CI's MySQL 8 job had not run since 2026-11-05 because the build failed (fixed below); before that, migrations passed on MySQL 8 but PHPUnit on MySQL failed. The ~30 migrations since 2026-11-04 have **not yet run on MySQL 8**. A static scan found no MySQL-incompatible column defaults, TEXT/JSON indexes or over-long key names. |
| Build | **Fixed and verified** | `composer install` ran the startup guard during `package:discover` and failed with no `.env` (CI red since 2026-11-05). The guard now skips build commands; reproduced on a clean export (`ProductionInfrastructureTest`). |
| Queue workers | Verified (config) / on first deploy | Notification queue name is configurable and tested. On Cloud: dispatch a notification and see it in the Queues dashboard. |
| Cache | Verified (config) | `database` store; `cache` and `cache_locks` tables exist (migration `0001_01_01_000001`). |
| Session security | Verified | Guard refuses to boot without `SESSION_SECURE_COOKIE=true`; HTTP-only and SameSite=lax cookies; HTTPS seen correctly behind the proxy (test). |
| Storage | Verified (config) / on first deploy | Guard requires each media disk to be the private local root or an S3 bucket that is not public (test). On Cloud: upload a flyer and an ID document, redeploy, confirm both still load. |
| Database backups | On first deploy | Set the Laravel MySQL cluster's backup retention to at least 14 days (max 30). |
| Recovery | On first deploy | Restore a backup into a **new** cluster (Cloud never restores in place), attach it to a staging environment, run `scripts/production-restore-drill.sh` checks, record the result. |
| HTTPS | Verified | Proxies trusted; `URL::forceScheme('https')`; HSTS (1 year) on HTTPS responses (test). |
| Environment | Verified | Guard fails on: debug on, non-HTTPS `APP_URL`, insecure session cookie, non-MySQL DB, `log`/`array` mail, `sync` queue, `array` cache, missing daily+Slack logging, media/ID disk that is neither a private local root nor a private bucket. |
| Monitoring and logging | Verified | Daily logs (14 days) + Slack alerts for exceptions, failed jobs, failed payments and reconciliation mismatches. Alerts are best effort: an unreachable webhook never fails a request or rolls back a payment. Secrets are redacted from every record (`RedactSensitiveLogData`, unit-tested). Cloud's Logs and Queues dashboards add platform metrics. |

## Outstanding before go-live

* **Run the MySQL 8 suite.** With the build fixed, CI's MySQL job runs again on the next push. Its earlier
  PHPUnit-on-MySQL failures (2026-10-31 to 2026-11-03) must be read and fixed; GitHub only shows logs to a
  signed-in user (`gh auth login`).
* **Back up uploaded media.** Cloud does not back up bucket objects. Copy the bucket regularly to a second bucket
  or provider (for example a scheduled `rclone sync`), or flyers, menu photos and ID documents cannot be recovered.
* **First deploy:** run the "On first deploy" rows above and record the results in
  `docs/production-readiness-findings.md`.

Sources: [Managed Queues](https://laravel.com/cloud/docs/queues),
[Laravel deploy guide](https://laravel.com/cloud/docs/deploy-guides/laravel),
[Laravel MySQL](https://laravel.com/cloud/docs/resources/databases/laravel-mysql.md),
[Laravel Object Storage](https://laravel.com/cloud/docs/resources/object-storage.md).
