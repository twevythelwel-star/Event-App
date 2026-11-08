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
                Log::channel('slack')->error('queue.job_failed', ['queue' => $event->job->getQueue(), 'job' => $event->job->resolveName(),
                    'uuid' => $event->job->uuid(), 'exception_type' => $event->exception::class]);
            }
        });
        DB::listen(function ($query): void {
            if ($query->time >= (int) config('app.slow_query_threshold_ms', 250)) {
                Log::warning('database.slow_query', ['connection' => $query->connectionName, 'duration_ms' => $query->time,
                    'query_fingerprint' => hash('sha256', preg_replace('/\s+/', ' ', trim($query->sql)))]);
            }
        });
        if ($this->app->environment('production')) {
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
            if (config('logging.default') !== 'daily'
                || !str_starts_with((string) config('logging.channels.slack.url'), 'https://')) {
                throw new RuntimeException('Production requires retained daily logs and a configured HTTPS critical-alert webhook.');
            }
            if (config('logging.default') !== 'stack'
                || !in_array('slack', config('logging.channels.stack.channels', []), true)
                || !str_starts_with((string) config('logging.channels.slack.url'), 'https://')) {
                throw new RuntimeException('Production requires durable logs and a configured HTTPS critical-alert webhook (LOG_STACK=daily,slack).');
            }
            if (!str_contains((string) config('filesystems.disks.local.root'), '/storage/app/private')
                || !is_dir((string) config('filesystems.disks.local.root'))
                || !is_writable((string) config('filesystems.disks.local.root'))) {
                throw new RuntimeException('Production private media storage is not configured to a durable private root.');
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
    }
}
