<?php

declare(strict_types=1);

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\OrderOperationsController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SupportRequestsController;
use App\Http\Controllers\AttendeeDashboardController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HostApplicationController;
use App\Http\Controllers\HostDashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NotificationWebhookController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RoleSwitchController;
use App\Http\Controllers\StaffAssignmentController;
use App\Http\Controllers\SystemAdminDashboardController;
use App\Http\Controllers\WorkerTerminalController;
use App\Http\Middleware\EnsureAccountType;
use App\Http\Middleware\VerifyEmailWebhookSignature;
use App\Http\Middleware\VerifyTwilioSignature;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Island Events Enterprise Ticketing & Authentication
|--------------------------------------------------------------------------
*/

// Public Event Browsing (subject to configurable require_login_for_event_browsing policy)
Route::get('/', [EventController::class, 'index'])->name('home');
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{eventSlug}/menu', [MenuController::class, 'index'])->name('events.menu.index');

// Provider Delivery Webhooks (signature-verified; exempt from CSRF since providers cannot send a token)
Route::prefix('api/webhooks')
    ->withoutMiddleware([PreventRequestForgery::class])
    ->middleware('throttle:120,1')
    ->group(function () {
        Route::post('/twilio/status-callback', [NotificationWebhookController::class, 'handleTwilioStatusCallback'])
            ->middleware(VerifyTwilioSignature::class)
            ->name('webhooks.twilio.status');
        Route::post('/email', [NotificationWebhookController::class, 'handleEmailWebhook'])
            ->middleware(VerifyEmailWebhookSignature::class)
            ->name('webhooks.email');
    });

// Public Guest Routes (Rate-limited against automated abuse)
Route::middleware(['guest'])->group(function () {
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');

    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:3,1');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');
});

// Authenticated Routes
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Authenticated Food Ordering & Checkout
    Route::post('/events/{event}/order', [OrderController::class, 'store'])->name('orders.store')->middleware('throttle:15,1');
    Route::get('/orders/retrieve', [OrderController::class, 'retrieve'])->name('orders.retrieve');

    // Event Host Application & Approval Workflow
    Route::get('/host/apply', [HostApplicationController::class, 'showApplyForm'])->name('host.apply');
    Route::post('/host/apply', [HostApplicationController::class, 'submit'])->name('host.apply.submit')->middleware('throttle:5,1');

    // Email verification
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])->name('verification.send')->middleware('throttle:3,1');

    // Context / Role switcher for dual-acting users
    Route::post('/context/switch', [RoleSwitchController::class, 'switchContext'])->name('context.switch');

    // Scoped Account Settings
    Route::get('/settings', [AccountSettingsController::class, 'show'])->name('settings.show');
    Route::post('/settings/profile', [AccountSettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/settings/password', [AccountSettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/currency', [AccountSettingsController::class, 'updateCurrency'])->name('settings.currency');

    // Event & Flyer Management
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{id}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{id}', [EventController::class, 'update'])->name('events.update');
    Route::post('/events/{id}/flyer', [EventController::class, 'uploadFlyer'])->name('events.flyer.upload');
    Route::delete('/events/{id}/flyer', [EventController::class, 'removeFlyer'])->name('events.flyer.remove');
    Route::post('/events/{id}/publish', [EventController::class, 'publish'])->name('events.publish');
    Route::post('/events/{id}/unpublish', [EventController::class, 'unpublish'])->name('events.unpublish');
    Route::post('/events/{id}/toggle-ordering', [EventController::class, 'toggleOrdering'])->name('events.toggle-ordering');

    // Event Menu Management (Event Owners)
    Route::post('/events/{eventSlug}/menu', [MenuController::class, 'store'])->name('events.menu.store')->middleware('throttle:30,1');
    Route::put('/events/{eventSlug}/menu/{itemId}', [MenuController::class, 'update'])->name('events.menu.update');
    Route::delete('/events/{eventSlug}/menu/{itemId}', [MenuController::class, 'destroy'])->name('events.menu.destroy');

    // Staff Claim Codes (Account Holder Self-Service)
    Route::post('/staff-codes/generate', [StaffAssignmentController::class, 'generateClaimCode'])->name('staff-codes.generate')->middleware('throttle:10,1');
    Route::post('/staff-codes/{id}/revoke', [StaffAssignmentController::class, 'revokeClaimCode'])->name('staff-codes.revoke');

    // Staff Assignment Offers (Recipient Response)
    Route::post('/staff-assignments/{id}/accept', [StaffAssignmentController::class, 'acceptAssignment'])->name('staff-assignments.accept');
    Route::post('/staff-assignments/{id}/decline', [StaffAssignmentController::class, 'declineAssignment'])->name('staff-assignments.decline');

    // Event Host Staff Management & Code Validation
    Route::get('/host/events/{event}/staff', [StaffAssignmentController::class, 'showEventStaff'])->name('host.events.staff');
    Route::post('/host/events/{event}/staff/verify-code', [StaffAssignmentController::class, 'verifyClaimCode'])->name('host.events.staff.verify-code')->middleware('throttle:20,1');
    Route::post('/host/events/{event}/staff/assign', [StaffAssignmentController::class, 'assignStaff'])->name('host.events.staff.assign');
    Route::post('/host/events/{event}/staff/{assignment}/revoke', [StaffAssignmentController::class, 'revokeAssignment'])->name('host.events.staff.revoke');

    // 1. Attendee Dashboard & Orders Wallet
    Route::get('/attendee/dashboard', [AttendeeDashboardController::class, 'index'])->name('attendee.dashboard');
    Route::post('/attendee/retrieve-ticket', [AttendeeDashboardController::class, 'retrieveTicket'])->name('attendee.retrieve-ticket');

    // 2. Event Host Command Center
    Route::get('/host/dashboard', [HostDashboardController::class, 'index'])->name('host.dashboard');

    // 3. Delegated Administrator Operations Desk (System Administrators also pass this gate)
    Route::middleware(EnsureAccountType::class . ':admin')->group(function () {
        Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/admin/host-applications', [HostApplicationController::class, 'indexAdmin'])->name('admin.host-applications');
        Route::post('/admin/host-applications/{id}/approve', [HostApplicationController::class, 'approve'])->name('admin.host-applications.approve');
        Route::post('/admin/host-applications/{id}/reject', [HostApplicationController::class, 'reject'])->name('admin.host-applications.reject');
    });

    // 4. System Administrator Root Governance
    Route::get('/system-admin/dashboard', [SystemAdminDashboardController::class, 'index'])
        ->middleware(EnsureAccountType::class . ':system_admin')
        ->name('system-admin.dashboard');

    // Event-Worker Scoped Terminals (Payment Clerk, Ticket Checker, Food Station Worker, Supervisor)
    Route::prefix('worker/events/{event}')->group(function () {
        Route::get('/terminal', [WorkerTerminalController::class, 'index'])->name('worker.terminal');
        Route::post('/scan', [WorkerTerminalController::class, 'scanTicket'])->name('worker.scan')->middleware('throttle:30,1');
        Route::post('/cash', [WorkerTerminalController::class, 'recordCash'])->name('worker.cash');
        Route::post('/collect', [WorkerTerminalController::class, 'collectFood'])->name('worker.collect');
    });
});

// Single-Page App JSON API (cookie session + XSRF token, served same-origin through the Vite proxy)
Route::prefix('api/v1')->group(function () {
    Route::get('/session', [SessionController::class, 'show'])->name('api.session.show');
    Route::post('/session', [SessionController::class, 'store'])->name('api.session.store')->middleware('throttle:5,1');
    Route::post('/register', [SessionController::class, 'register'])->name('api.register')->middleware('throttle:10,1');
    Route::get('/events', [CatalogController::class, 'events'])->name('api.events.index');

    Route::middleware(['auth', 'active'])->group(function () {
        Route::delete('/session', [SessionController::class, 'destroy'])->name('api.session.destroy');
        Route::post('/events/{eventSlug}/orders', [CatalogController::class, 'placeOrder'])->name('api.orders.store')->middleware('throttle:15,1');
        Route::get('/orders', [CatalogController::class, 'myOrders'])->name('api.orders.index');

        // Host verification & event-day scanner; access is checked per order against its organizer/event
        Route::get('/operations/orders', [OrderOperationsController::class, 'index'])->name('api.operations.orders');
        Route::post('/operations/lookup', [OrderOperationsController::class, 'lookup'])->name('api.operations.lookup')->middleware('throttle:60,1');
        Route::post('/operations/orders/{orderId}/payment', [OrderOperationsController::class, 'updatePayment'])->name('api.operations.payment');
        Route::post('/operations/orders/{orderId}/fulfillment', [OrderOperationsController::class, 'updateFulfillment'])->name('api.operations.fulfillment');
        Route::post('/operations/orders/{orderId}/items/{itemId}/handover', [OrderOperationsController::class, 'handover'])->name('api.operations.handover');
        Route::post('/operations/orders/{orderId}/close', [OrderOperationsController::class, 'close'])->name('api.operations.close');
        Route::post('/operations/orders/{orderId}/refund', [OrderOperationsController::class, 'refund'])->name('api.operations.refund');
        Route::get('/operations/cash', [OrderOperationsController::class, 'cashLedger'])->name('api.operations.cash.index');
        Route::post('/operations/cash', [OrderOperationsController::class, 'recordCash'])->name('api.operations.cash.store')->middleware('throttle:60,1');
        Route::post('/operations/cash/{receiptId}/reverse', [OrderOperationsController::class, 'reverseCash'])->name('api.operations.cash.reverse');

        // Attendee requests: Event Host applications and order disputes
        Route::get('/host-applications', [SupportRequestsController::class, 'myHostApplications'])->name('api.host-applications.mine');
        Route::post('/host-applications', [SupportRequestsController::class, 'submitHostApplication'])->name('api.host-applications.store')->middleware('throttle:5,1');
        Route::get('/disputes', [SupportRequestsController::class, 'myDisputes'])->name('api.disputes.mine');
        Route::post('/disputes', [SupportRequestsController::class, 'submitDispute'])->name('api.disputes.store')->middleware('throttle:10,1');

        // Administrator review (System Administrators also pass this gate)
        Route::prefix('admin')->middleware(EnsureAccountType::class . ':admin')->group(function () {
            Route::get('/host-applications', [SupportRequestsController::class, 'adminHostApplications'])->name('api.admin.host-applications');
            Route::post('/host-applications/{id}/approve', [SupportRequestsController::class, 'approveHostApplication'])->name('api.admin.host-applications.approve');
            Route::post('/host-applications/{id}/reject', [SupportRequestsController::class, 'rejectHostApplication'])->name('api.admin.host-applications.reject');
            Route::get('/disputes', [SupportRequestsController::class, 'adminDisputes'])->name('api.admin.disputes');
            Route::post('/disputes/{id}/status', [SupportRequestsController::class, 'updateDispute'])->name('api.admin.disputes.status');
        });
    });
});

// Public Event Detail - registered last so the {id} wildcard cannot shadow static paths such as /events/create
Route::get('/events/{id}', [EventController::class, 'show'])->name('events.show');
