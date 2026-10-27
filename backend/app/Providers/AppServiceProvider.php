<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sign-in attempts are limited per account (to stop password guessing) and, more loosely, per IP.
        // A plain per-IP limit would lock out a whole venue: staff and attendees share one public IP on event Wi-Fi.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:' . strtolower(trim((string) $request->input('email'))) . '|' . $request->ip()),
            Limit::perMinute(60)->by('login-ip:' . $request->ip()),
        ]);
    }
}
