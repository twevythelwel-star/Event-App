<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\LocationManagementController;
use App\Http\Controllers\Admin\EventOversightController;
use App\Http\Controllers\Admin\OrderOversightController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\PlatformSettingsController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Support\Facades\Route;

/*
| Administration console. Every route sits behind the session, the active-account check and the
| central admin.access gate; each controller action then authorizes its own ability and record scope.
*/
Route::middleware(['auth', 'active', 'can:' . Admin::ACCESS])
    ->prefix('admin/console')
    ->name('admin.console.')
    ->group(function () {
        Route::get('/', [OverviewController::class, 'index'])->name('overview');

        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/status', [UserManagementController::class, 'updateStatus'])->name('users.status')->middleware('throttle:20,1');
        Route::post('/users/{user}/roles', [UserManagementController::class, 'grantRole'])->name('users.roles.grant')->middleware('throttle:20,1');
        Route::post('/users/{user}/roles/{role}/revoke', [UserManagementController::class, 'revokeRole'])->name('users.roles.revoke')->middleware('throttle:20,1');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles/administrators/{user}', [RoleController::class, 'updateAdministrator'])->name('roles.administrators.update')->middleware('throttle:20,1');

        Route::get('/events', [EventOversightController::class, 'index'])->name('events.index');
        Route::get('/events/{event}', [EventOversightController::class, 'show'])->name('events.show');
        Route::post('/events/{event}/moderate', [EventOversightController::class, 'moderate'])->name('events.moderate')->middleware('throttle:20,1');

        Route::get('/orders', [OrderOversightController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderOversightController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/review', [OrderOversightController::class, 'review'])->name('orders.review')->middleware('throttle:30,1');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export')->middleware('throttle:10,1');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');

        Route::get('/settings', [PlatformSettingsController::class, 'edit'])->name('settings.edit');
        Route::post('/settings', [PlatformSettingsController::class, 'update'])->name('settings.update')->middleware('throttle:10,1');

        Route::get('/locations', [LocationManagementController::class, 'index'])->name('locations.index');
        Route::post('/locations/states', [LocationManagementController::class, 'storeState'])->name('locations.states.store')->middleware('throttle:20,1');
        Route::patch('/locations/states/{state}', [LocationManagementController::class, 'updateState'])->name('locations.states.update')->middleware('throttle:30,1');
        Route::post('/locations/markets', [LocationManagementController::class, 'storeMarket'])->name('locations.markets.store')->middleware('throttle:20,1');
        Route::patch('/locations/markets/{market}', [LocationManagementController::class, 'updateMarket'])->name('locations.markets.update')->middleware('throttle:30,1');
    });
