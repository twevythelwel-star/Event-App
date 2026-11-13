<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemPlatformSetting;
use App\Services\AuditLogService;
use App\Services\EventQr\TicketQrDesignSystem;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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

        $settings = SystemPlatformSetting::firstOrCreate([]);
        $designs = app(TicketQrDesignSystem::class);
        $qr = app(\App\Services\EventQr\TicketQrService::class);
        $previews = [];
        foreach (TicketQrDesignSystem::PRESETS as $key => $spec) {
            $design = $designs->resolve(null, ['preset' => $key]);
            $previews[$key] = ['svg' => $qr->previewSvg($design), 'fallback' => $design['fallback_reason'] ?? null];
        }

        return view('admin.qr-codes.index', [
            'settings' => $settings,
            'presets' => self::PRESETS,
            'ticketControls' => $designs->controls(),
            'ticketPresets' => TicketQrDesignSystem::PRESETS,
            'ticketPreviews' => $previews,
        ]);
    }

    /**
     * Global controls for attendee ticket QR designs: switch themed codes off everywhere, choose which presets
     * hosts may use and the default, allow or forbid custom colours and centre marks, and raise (never lower)
     * the minimum contrast. Readability rules themselves cannot be switched off.
     */
    public function updateTicketControls(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);
        $presetKeys = array_keys(TicketQrDesignSystem::PRESETS);
        $data = $request->validate([
            'enabled' => ['boolean'],
            'default_preset' => ['required', 'string', Rule::in(array_merge(['auto'], $presetKeys))],
            'allowed_presets' => ['required', 'array', 'min:1'],
            'allowed_presets.*' => ['string', Rule::in($presetKeys)],
            'allow_custom_palette' => ['boolean'],
            'allow_center_logo' => ['boolean'],
            'min_contrast' => ['required', 'numeric', 'min:' . TicketQrDesignSystem::MIN_CONTRAST, 'max:21'],
        ], ['min_contrast.min' => 'The minimum contrast cannot go below 7:1; that is the floor for reliable scanning.']);

        if ($data['default_preset'] !== 'auto' && !in_array($data['default_preset'], $data['allowed_presets'], true)) {
            return $this->ticketControlsError($request, 'The default design must be one of the allowed designs.');
        }

        $controls = TicketQrDesignSystem::normaliseControls([
            'enabled' => $request->boolean('enabled'),
            'default_preset' => $data['default_preset'],
            'allowed_presets' => array_values(array_unique($data['allowed_presets'])),
            'allow_custom_palette' => $request->boolean('allow_custom_palette'),
            'allow_center_logo' => $request->boolean('allow_center_logo'),
            'min_contrast' => (float) $data['min_contrast'],
        ]);

        $settings = SystemPlatformSetting::firstOrCreate([]);
        $before = $settings->ticket_qr_controls;
        $settings->ticket_qr_controls = $controls;
        $settings->save();

        AuditLogService::log(
            action: 'ticket_qr_controls_changed',
            targetEntity: 'system_platform_settings',
            targetId: (string) $settings->id,
            details: 'Ticket QR controls: ' . json_encode($before) . ' -> ' . json_encode($controls),
            actor: $request->user(),
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Ticket QR controls updated.', 'controls' => $controls]);
        }

        return redirect()->route('admin.console.qr-codes.index')->with('success', 'Ticket QR controls updated. Tickets pick up the change the next time they open.');
    }

    private function ticketControlsError(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'errors' => ['default_preset' => [$message]]], 422);
        }

        return back()->withErrors(['default_preset' => $message])->withInput();
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        $settings = SystemPlatformSetting::firstOrCreate([]);

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

        if (!$request->expectsJson()) {
            return redirect()->route('admin.console.qr-codes.index')->with('success', 'QR code configuration updated.');
        }

        return response()->json([
            'success' => true,
            'message' => 'QR code configuration updated.',
            'settings' => $settings->fresh(),
        ]);
    }
}
