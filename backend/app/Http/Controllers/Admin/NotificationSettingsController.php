<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemPlatformSetting;
use App\Services\AuditLogService;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NotificationSettingsController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        $settings = SystemPlatformSetting::first();

        return view('admin.notifications.index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        $settings = SystemPlatformSetting::first();

        $validated = $request->validate([
            'email_notifications_enabled' => ['boolean'],
            'sms_notifications_enabled' => ['boolean'],
            'push_notifications_enabled' => ['boolean'],
        ]);

        $validated['email_notifications_enabled'] = $request->boolean('email_notifications_enabled');
        $validated['sms_notifications_enabled'] = $request->boolean('sms_notifications_enabled');
        $validated['push_notifications_enabled'] = $request->boolean('push_notifications_enabled');

        $changes = [];
        foreach ($validated as $key => $value) {
            if ($settings->{$key} != $value) {
                $changes[] = "{$key}: {$settings->{$key}} -> {$value}";
                $settings->{$key} = $value;
            }
        }

        if ($changes !== []) {
            $settings->save();

            AuditLogService::log(
                action: 'notification_settings_changed',
                targetEntity: 'system_platform_settings',
                targetId: (string) $settings->id,
                details: implode('; ', $changes),
                actor: $request->user(),
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification settings updated.',
            'settings' => $settings->fresh(),
        ]);
    }
}
