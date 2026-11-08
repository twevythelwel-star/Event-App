<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureAccountType;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\LogSlowRequests;
use App\Http\Middleware\ProhibitedActionGuard;
use App\Http\Middleware\RequireActiveWorkerAssignment;
use App\Http\Middleware\RequireWorkerPermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(LogSlowRequests::class);
        // Material policy changes are acknowledged at the next interaction; restricted actions wait until they are.
        $middleware->web(append: \App\Http\Middleware\RequirePolicyAcknowledgement::class);
        $middleware->alias([
            'active' => EnsureAccountActive::class,
            'account.type' => EnsureAccountType::class,
            'tenant' => EnsureTenantAccess::class,
            'worker.active' => RequireActiveWorkerAssignment::class,
            'worker.permission' => RequireWorkerPermission::class,
            'worker.prohibited' => ProhibitedActionGuard::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $exception): void {
            if (app()->environment('production')) {
                \Illuminate\Support\Facades\Log::channel('slack')->error('application.exception', [
                    'exception_type' => $exception::class,
                    'path' => app()->runningInConsole() ? 'console' : request()->path(),
                    'request_id' => app()->runningInConsole() ? null : request()->header('X-Request-ID'),
                ]);
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Never put typed passwords back into the session after a validation error.
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'new_password', 'new_password_confirmation']);
    })->create();
