<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureAccountType;
use App\Http\Middleware\EnsureTenantAccess;
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
        $middleware->alias([
            'active' => EnsureAccountActive::class,
            'account.type' => EnsureAccountType::class,
            'tenant' => EnsureTenantAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Never put typed passwords back into the session after a validation error.
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'new_password', 'new_password_confirmation']);
    })->create();
