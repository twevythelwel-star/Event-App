<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Policies\EventPolicy;
use App\Policies\EventWorkerAssignmentPolicy;
use App\Policies\OrderPolicy;
use App\Support\AdminAuthorization;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Published policy versions, read once per request.
        $this->app->scoped(\App\Services\Compliance\PolicyCatalog::class);
        // Approved library photos, read once per request.
        $this->app->scoped(\App\Services\PhotoLibrary::class);
    }

    /** Artisan commands that build or cache the application before it is configured for its environment. */
    private static function isBuildCommand(): bool
    {
        if (!app()->runningInConsole()) {
            return false;
        }
        $command = (string) ($_SERVER['argv'][1] ?? '');

        return in_array($command, ['package:discover', 'config:cache', 'config:clear', 'route:cache', 'route:clear', 'view:cache',
            'view:clear', 'event:cache', 'event:clear', 'optimize', 'optimize:clear', 'key:generate', 'list', 'about'], true);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        EventFacade::listen(\Illuminate\Queue\Events\JobFailed::class, function ($event): void {
            Log::error('queue.job_failed', ['connection' => $event->connectionName, 'queue' => $event->job->getQueue(),
                'job' => $event->job->resolveName(), 'uuid' => $event->job->uuid(), 'exception' => $event->exception::class]);
            if ($this->app->environment('production')) {
                try { // best effort: an unreachable alert webhook must not break failed-job handling
                    Log::channel('slack')->error('queue.job_failed', ['queue' => $event->job->getQueue(), 'job' => $event->job->resolveName(),
                        'uuid' => $event->job->uuid(), 'exception_type' => $event->exception::class]);
                } catch (\Throwable) {
                }
            }
        });
        DB::listen(function ($query): void {
            if ($query->time >= (int) config('app.slow_query_threshold_ms', 250)) {
                Log::warning('database.slow_query', ['connection' => $query->connectionName, 'duration_ms' => $query->time,
                    'query_fingerprint' => hash('sha256', preg_replace('/\s+/', ' ', trim($query->sql)))]);
            }
        });
        // Trusted proxies (see bootstrap/app.php): "*" trusts the platform load balancer; a list narrows it.
        $proxies = (string) config('security.trusted_proxies', '*');
        \Illuminate\Http\Middleware\TrustProxies::at($proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxies)))));

        // The production configuration guard stops the app serving requests, running migrations or working the
        // queue with unsafe settings. It does not run for build steps (composer's package:discover, the cache and
        // optimize commands), which run before the real environment exists (CI, Laravel Cloud's build).
        if ($this->app->environment('production') && !self::isBuildCommand()) {
            if (config('app.env') !== 'production' || config('app.debug') !== false) {
                throw new RuntimeException('Production requires APP_ENV=production and APP_DEBUG=false.');
            }
            if (!str_starts_with((string) config('app.url'), 'https://')) {
                throw new RuntimeException('Production requires APP_URL to use HTTPS.');
            }
            if (config('session.secure') !== true) {
                throw new RuntimeException('Production requires SESSION_SECURE_COOKIE=true.');
            }
            if (config('database.default') !== 'mysql' || !filled(config('database.connections.mysql.database'))
                || !filled(config('database.connections.mysql.username')) || !filled(config('database.connections.mysql.password'))) {
                throw new RuntimeException('Production requires configured MySQL database credentials.');
            }
            if (in_array(config('mail.default'), ['log', 'array', 'failover'], true)) {
                throw new RuntimeException('Production requires a real mail provider.');
            }
            if (config('queue.default') === 'sync' || config('cache.default') === 'array') {
                throw new RuntimeException('Production requires durable queue and cache stores.');
            }
            $logChannels = (array) config('logging.channels.stack.channels', []);
            if (config('logging.default') !== 'stack'
                || !in_array('daily', $logChannels, true)
                || !in_array('slack', $logChannels, true)
                || !str_starts_with((string) config('logging.channels.slack.url'), 'https://')) {
                throw new RuntimeException('Production requires durable daily logs and a configured HTTPS critical-alert webhook (LOG_CHANNEL=stack, LOG_STACK=daily,slack).');
            }
            // Uploaded media and host ID documents: on the private local root (self-managed), or on an S3-compatible
            // bucket (MEDIA_DISK / HOST_ID_STORAGE_DISK, required on hosts with an ephemeral filesystem).
            foreach (array_unique([\App\Services\MediaStorageService::diskName(), (string) config('compliance.host_verification.storage_disk', 'local')]) as $diskName) {
                $disk = (array) config("filesystems.disks.{$diskName}");
                $ok = match ($disk['driver'] ?? null) {
                    'local' => str_contains((string) ($disk['root'] ?? ''), '/storage/app/private')
                        && is_dir((string) $disk['root']) && is_writable((string) $disk['root']),
                    's3' => filled($disk['bucket'] ?? null) && ($disk['visibility'] ?? 'private') !== 'public',
                    default => false,
                };
                if (!$ok) {
                    throw new RuntimeException("Production storage disk '{$diskName}' is not a durable private root or a private S3 bucket.");
                }
            }
            URL::forceScheme('https');
        }

        AdminAuthorization::register();

        // Register Laravel policies
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(EventWorkerAssignment::class, EventWorkerAssignmentPolicy::class);

        // Sign-in attempts are limited per account (to stop password guessing) and, more loosely, per IP.
        // A plain per-IP limit would lock out a whole venue: staff and attendees share one public IP on event Wi-Fi.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:' . strtolower(trim((string) $request->input('email'))) . '|' . $request->ip()),
            Limit::perMinute(60)->by('login-ip:' . $request->ip()),
        ]);

        // Sensitive compliance and verification endpoints, limited per signed-in person (per IP when signed out):
        // identity documents (uploads, decisions, opening a document) and compliance documents and reviews.
        RateLimiter::for('identity-verification', fn (Request $request) => Limit::perMinute(10)
            ->by('identity:' . ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('compliance-sensitive', fn (Request $request) => Limit::perMinute(30)
            ->by('compliance:' . ($request->user()?->id ?? $request->ip())));

        // Compliance review notifications (app/Listeners). WriteComplianceAuditLog is found by event discovery, through
        // its ComplianceAuditable type hint; a subscriber's methods are not, so it is registered here.
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\SendComplianceNotifications::class);
    }
}
