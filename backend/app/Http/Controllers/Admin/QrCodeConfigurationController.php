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

class QrCodeConfigurationController extends Controller
{
    private const PRESETS = [
        'geometric' => 'Geometric',
        'circular' => 'Circular',
        'neon' => 'Neon',
        'premium_minimal' => 'Premium Minimal',
        'festival' => 'Festival',
        'editorial' => 'Editorial',
        'custom_event_theme' => 'Custom Event Theme',
        'random_mix' => 'Random Mix',
    ];

    public function index(): View
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        $settings = SystemPlatformSetting::first();

        return view('admin.qr-codes.index', [
            'settings' => $settings,
            'presets' => self::PRESETS,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        $settings = SystemPlatformSetting::first();

        $validated = $request->validate([
            'qr_design_version' => ['required', 'integer', 'min:1', 'max:10'],
            'qr_default_preset' => ['required', 'string', 'in:' . implode(',', array_keys(self::PRESETS))],
            'qr_enforce_design_rules' => ['boolean'],
        ]);

        $validated['qr_enforce_design_rules'] = $request->boolean('qr_enforce_design_rules');

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
                action: 'qr_code_configuration_changed',
                targetEntity: 'system_platform_settings',
                targetId: (string) $settings->id,
                details: implode('; ', $changes),
                actor: $request->user(),
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'QR code configuration updated.',
            'settings' => $settings->fresh(),
        ]);
    }
}
