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

class FraudPreventionController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        $settings = SystemPlatformSetting::first();

        return view('admin.fraud.index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        $settings = SystemPlatformSetting::first();

        $validated = $request->validate([
            'fraud_detection_enabled' => ['boolean'],
            'max_orders_per_ip_hour' => ['required', 'integer', 'min:1', 'max:100'],
            'max_amount_per_order_minor' => ['required', 'integer', 'min:100', 'max:10000000'], // $0.01 to $100,000
            'require_3ds_above_minor' => ['required', 'integer', 'min:0', 'max:10000000'],
        ]);

        $validated['fraud_detection_enabled'] = $request->boolean('fraud_detection_enabled');

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
                action: 'fraud_prevention_changed',
                targetEntity: 'system_platform_settings',
                targetId: (string) $settings->id,
                details: implode('; ', $changes),
                actor: $request->user(),
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Fraud prevention settings updated.',
            'settings' => $settings->fresh(),
        ]);
    }
}
