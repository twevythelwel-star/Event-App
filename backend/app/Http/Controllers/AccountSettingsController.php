<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateHostSettingsRequest;
use App\Http\Requests\UpdatePersonalProfileRequest;
use App\Http\Requests\UpdatePlatformSettingsRequest;
use App\Models\HostPaymentPolicy;
use App\Models\SystemPlatformSetting;
use App\Services\AccountSettingsService;
use App\Services\AuditLogService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountSettingsController extends Controller
{
    /**
     * Get settings visible to current authenticated user.
     */
    public function show(Request $request): JsonResponse|\Illuminate\View\View
    {
        $user = $request->user();
        $settings = AccountSettingsService::getSettingsForUser($user);

        if ($request->wantsJson()) {
            return response()->json($settings);
        }

        return view('dashboards.settings', [
            'settings' => $settings,
            'user' => $user,
        ]);
    }

    /**
     * Update personal profile information.
     */
    public function updateProfile(UpdatePersonalProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->name = $validated['name'];
        $user->email = strtolower($validated['email']);
        if (isset($validated['phone'])) {
            $user->phone = $validated['phone'];
        }
        if (isset($validated['preferred_display_currency'])) {
            $user->preferred_display_currency = $validated['preferred_display_currency'];
        }
        $user->save();

        AuditLogService::log(
            action: 'profile_updated',
            targetEntity: 'users',
            targetId: (string) $user->id,
            details: "User updated profile name to '{$user->name}' and email to '{$user->email}'.",
            actor: $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Personal profile updated successfully.',
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'preferred_display_currency' => $user->preferred_display_currency,
            ],
        ]);
    }

    /**
     * Change user password.
     */
    public function updatePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (!password_verify($validated['current_password'], $user->password)) {
            return response()->json(['error' => 'Current password does not match.'], 422);
        }

        $user->password = password_hash($validated['new_password'], PASSWORD_DEFAULT);
        $user->save();

        AuditLogService::log(
            action: 'password_rotated',
            targetEntity: 'users',
            targetId: (string) $user->id,
            details: "User {$user->email} rotated password across active sessions.",
            actor: $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Password rotated successfully.',
        ]);
    }

    /**
     * Update Attendee preferred display currency.
     * Invariant: Never changes currency charged for an order.
     */
    public function updateDisplayCurrency(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', 'in:JMD,USD'],
        ]);

        $result = AccountSettingsService::updateAttendeeDisplayCurrency($request->user(), $validated['currency']);
        return response()->json($result);
    }

    /**
     * Update Event Host event defaults and payment policies.
     */
    public function updateHostSettings(UpdateHostSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = TenantContext::id();

        $validated = $request->validated();

        $eventDefaults = AccountSettingsService::updateHostEventDefaults($tenantId, $validated, $user);

        // Update payment policies
        $policies = HostPaymentPolicy::firstOrNew(['organizer_id' => $tenantId]);
        if (isset($validated['enable_cash'])) {
            $policies->enable_cash = (bool) $validated['enable_cash'];
        }
        if (isset($validated['zelle_instructions'])) {
            $policies->zelle_instructions = $validated['zelle_instructions'];
        }
        if (isset($validated['cash_app_instructions'])) {
            $policies->cash_app_instructions = $validated['cash_app_instructions'];
        }
        if (isset($validated['bank_transfer_instructions'])) {
            $policies->bank_transfer_instructions = $validated['bank_transfer_instructions'];
        }
        if (isset($validated['require_transfer_reference'])) {
            $policies->require_transfer_reference = (bool) $validated['require_transfer_reference'];
        }
        if (isset($validated['require_sender_name'])) {
            $policies->require_sender_name = (bool) $validated['require_sender_name'];
        }
        if (isset($validated['require_receipt_snapshot'])) {
            $policies->require_receipt_snapshot = (bool) $validated['require_receipt_snapshot'];
        }
        if (isset($validated['partial_payments_enabled'])) {
            $policies->partial_payments_enabled = (bool) $validated['partial_payments_enabled'];
        }
        if (isset($validated['deposit_percentage'])) {
            $policies->deposit_percentage = (int) $validated['deposit_percentage'];
        }
        if (isset($validated['refund_policy_deadline_hours'])) {
            $policies->refund_policy_deadline_hours = (int) $validated['refund_policy_deadline_hours'];
        }
        if (isset($validated['refund_policy_notes'])) {
            $policies->refund_policy_notes = $validated['refund_policy_notes'];
        }
        $policies->save();

        return response()->json([
            'success' => true,
            'message' => 'Event settings and payment policies saved successfully. Historical orders remain protected.',
            'event_defaults' => $eventDefaults,
            'payment_policies' => $policies,
        ]);
    }

    /**
     * Update System Administrator platform settings.
     */
    public function updatePlatformSettings(UpdatePlatformSettingsRequest $request): JsonResponse
    {
        $sys = SystemPlatformSetting::firstOrNew([]);
        $sys->fill($request->validated());
        $sys->save();

        AuditLogService::log(
            action: 'platform_settings_updated',
            targetEntity: 'system_platform_settings',
            targetId: (string) $sys->id,
            details: "System Administrator updated platform configuration.",
            actor: $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Platform settings updated successfully.',
            'platform_settings' => $sys,
        ]);
    }
}
