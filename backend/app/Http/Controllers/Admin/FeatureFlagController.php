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

class FeatureFlagController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        $settings = SystemPlatformSetting::first();

        return view('admin.feature-flags.index', [
            'settings' => $settings,
            'availableFlags' => $this->getAvailableFlags(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        $settings = SystemPlatformSetting::first();

        $validated = $request->validate([
            'feature_flags' => ['nullable', 'array'],
            'feature_flags.*' => ['string', 'in:' . implode(',', array_keys($this->getAvailableFlags()))],
        ]);

        $oldFlags = $settings->feature_flags ?? [];
        $newFlags = $validated['feature_flags'] ?? [];

        $changes = [];
        if ($oldFlags !== $newFlags) {
            $changes[] = 'feature_flags: ' . json_encode($oldFlags) . ' -> ' . json_encode($newFlags);
            $settings->feature_flags = $newFlags;
            $settings->save();

            AuditLogService::log(
                action: 'feature_flags_changed',
                targetEntity: 'system_platform_settings',
                targetId: (string) $settings->id,
                details: implode('; ', $changes),
                actor: $request->user(),
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Feature flags updated.',
            'settings' => $settings->fresh(),
        ]);
    }

    private function getAvailableFlags(): array
    {
        return [
            'enable_samsung_pay' => 'Enable Samsung Pay',
            'enable_cash_app_pay' => 'Enable Cash App Pay',
            'enable_multi_currency' => 'Enable Multi-Currency Support',
            'enable_dynamic_pricing' => 'Enable Dynamic Pricing',
            'enable_early_bird_discounts' => 'Enable Early Bird Discounts',
            'enable_group_discounts' => 'Enable Group Discounts',
            'enable_waitlist' => 'Enable Event Waitlist',
            'enable_rsvp' => 'Enable RSVP System',
            'enable_donation_tickets' => 'Enable Donation Tickets',
            'enable_gift_cards' => 'Enable Gift Cards',
        ];
    }
}
