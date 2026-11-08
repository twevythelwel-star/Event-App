<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ComplianceDatabaseController;
use App\Http\Controllers\Admin\FraudPreventionController;
use App\Http\Controllers\Admin\FeatureFlagController;
use App\Http\Controllers\Admin\LocationManagementController;
use App\Http\Controllers\Admin\NotificationSettingsController;
use App\Http\Controllers\Admin\PaymentConfigurationController;
use App\Http\Controllers\Admin\QrCodeConfigurationController;
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
        Route::post('/events/{event}/images', [EventOversightController::class, 'moderateImage'])->name('events.images.moderate')->middleware('throttle:30,1');

        Route::get('/orders', [OrderOversightController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderOversightController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/review', [OrderOversightController::class, 'review'])->name('orders.review')->middleware('throttle:30,1');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export')->middleware('throttle:10,1');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');

        Route::get('/settings', [PlatformSettingsController::class, 'edit'])->name('settings.edit');
        Route::post('/settings', [PlatformSettingsController::class, 'update'])->name('settings.update')->middleware('throttle:10,1');

        Route::get('/charges', [\App\Http\Controllers\Admin\ChargeRuleController::class, 'index'])->name('charges.index');
        Route::post('/charges', [\App\Http\Controllers\Admin\ChargeRuleController::class, 'store'])->name('charges.store')->middleware('throttle:20,1');
        Route::post('/charges/{rule}/toggle', [\App\Http\Controllers\Admin\ChargeRuleController::class, 'toggle'])->name('charges.toggle')->middleware('throttle:30,1');

        // The US state and local compliance database and its review workflow (ComplianceReviewWorkflow).
        Route::get('/compliance', [ComplianceDatabaseController::class, 'index'])->name('compliance.index');
        Route::get('/compliance/jurisdictions/{jurisdiction}', [ComplianceDatabaseController::class, 'show'])->name('compliance.jurisdictions.show');
        // Event-checklist rules, exceptions and local requirements, and the hosts' evidence to verify.
        Route::post('/compliance/jurisdictions/{jurisdiction}/rules', [\App\Http\Controllers\Admin\ComplianceRulesController::class, 'store'])->name('compliance.rules.store')->middleware('throttle:30,1');
        Route::post('/compliance/rules/{rule}/toggle', [\App\Http\Controllers\Admin\ComplianceRulesController::class, 'toggle'])->name('compliance.rules.toggle')->middleware('throttle:30,1');
        Route::post('/events/{event}/compliance-overrides', [\App\Http\Controllers\Admin\CompliancePublicationOverrideController::class, 'store'])->name('compliance.overrides.store')->middleware('throttle:20,1');
        Route::post('/compliance/overrides/{override}/revoke', [\App\Http\Controllers\Admin\CompliancePublicationOverrideController::class, 'revoke'])->name('compliance.overrides.revoke')->middleware('throttle:20,1');
        Route::get('/compliance/evidence',[\App\Http\Controllers\Admin\ComplianceRulesController::class, 'evidence'])->name('compliance.evidence.index');
        Route::post('/compliance/evidence/{evidence}/{action}', [\App\Http\Controllers\Admin\ComplianceRulesController::class, 'review'])->whereIn('action', ['start', 'verify', 'reject'])->name('compliance.evidence.review')->middleware('throttle:60,1');
        Route::post('/compliance/jurisdictions/{jurisdiction}/research', [ComplianceDatabaseController::class, 'startResearch'])->name('compliance.jurisdictions.research')->middleware('throttle:30,1');
        Route::post('/compliance/jurisdictions/{jurisdiction}/authorities', [ComplianceDatabaseController::class, 'storeAuthority'])->name('compliance.authorities.store')->middleware('throttle:30,1');
        Route::patch('/compliance/records/{type}/{id}', [ComplianceDatabaseController::class, 'update'])->whereIn('type', ['authority', 'requirement'])->whereNumber('id')->name('compliance.records.update')->middleware('throttle:60,1');
        Route::post('/compliance/records/{type}/{id}/{action}', [ComplianceDatabaseController::class, 'transition'])->whereIn('type', ['authority', 'requirement'])->whereNumber('id')->whereIn('action', ['submit', 'approve', 'request-changes', 'reset'])->name('compliance.records.transition')->middleware('throttle:60,1');

        Route::get('/locations', [LocationManagementController::class, 'index'])->name('locations.index');
        Route::post('/locations/states', [LocationManagementController::class, 'storeState'])->name('locations.states.store')->middleware('throttle:20,1');
        Route::patch('/locations/states/{state}', [LocationManagementController::class, 'updateState'])->name('locations.states.update')->middleware('throttle:30,1');
        Route::post('/locations/markets', [LocationManagementController::class, 'storeMarket'])->name('locations.markets.store')->middleware('throttle:20,1');
        Route::patch('/locations/markets/{market}', [LocationManagementController::class, 'updateMarket'])->name('locations.markets.update')->middleware('throttle:30,1');

        // Payment Configuration
        Route::get('/payments', [PaymentConfigurationController::class, 'index'])->name('payments.index');
        Route::post('/payments/{processor}/approve', [PaymentConfigurationController::class, 'approveProcessor'])->name('payments.approve')->middleware('throttle:20,1');
        Route::post('/payments/{processor}/disable', [PaymentConfigurationController::class, 'disableProcessor'])->name('payments.disable')->middleware('throttle:20,1');
        Route::get('/payments/webhooks', [PaymentConfigurationController::class, 'webhookHealth'])->name('payments.webhooks');
        Route::get('/payments/transactions', [PaymentConfigurationController::class, 'transactionLogs'])->name('payments.transactions');

        // QR Code Configuration
        Route::get('/qr-codes', [QrCodeConfigurationController::class, 'index'])->name('qr-codes.index');
        Route::post('/qr-codes', [QrCodeConfigurationController::class, 'update'])->name('qr-codes.update')->middleware('throttle:20,1');

        // Notification Settings
        Route::get('/notifications', [NotificationSettingsController::class, 'index'])->name('notifications.index');
        Route::post('/notifications', [NotificationSettingsController::class, 'update'])->name('notifications.update')->middleware('throttle:20,1');

        // Fraud Prevention
        Route::get('/fraud', [FraudPreventionController::class, 'index'])->name('fraud.index');
        Route::post('/fraud', [FraudPreventionController::class, 'update'])->name('fraud.update')->middleware('throttle:20,1');

        // Feature Flags
        Route::get('/feature-flags', [FeatureFlagController::class, 'index'])->name('feature-flags.index');
        Route::post('/feature-flags', [FeatureFlagController::class, 'update'])->name('feature-flags.update')->middleware('throttle:20,1');

    });
