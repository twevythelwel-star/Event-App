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
use App\Enums\TenantRole;
use App\Models\HostOperationalRule;
use App\Models\OrganizerUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

        // A host who owns an organizer edits that organizer's saved defaults, payment instructions and refund policy.
        $organizerId = $user->hasRole(\App\Enums\AccountType::EVENT_HOST) ? self::ownedOrganizerId($user) : null;

        return view('dashboards.settings', [
            'settings' => $settings,
            'user' => $user,
            'hostOrganizer' => $organizerId ? \App\Models\Organizer::find($organizerId) : null,
            'hostDefaults' => $organizerId ? \App\Models\HostEventSetting::firstOrNew(['organizer_id' => $organizerId]) : null,
            'hostPolicy' => $organizerId ? HostPaymentPolicy::firstOrNew(['organizer_id' => $organizerId]) : null,
            'hostRule' => $organizerId ? HostOperationalRule::firstOrNew(['organizer_id' => $organizerId]) : null,
        ]);
    }

    /**
     * Update personal profile information.
     */
    public function updateProfile(UpdatePersonalProfileRequest $request): JsonResponse|RedirectResponse
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

        return $this->respond($request, 'Your profile was saved.', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'preferred_display_currency' => $user->preferred_display_currency,
            ],
        ], '#profile');
    }

    /**
     * Change user password.
     */
    public function updatePassword(ChangePasswordRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (!password_verify($validated['current_password'], $user->password)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Current password does not match.'], 422);
            }

            return redirect(route('settings.show') . '#security')->withErrors(['current_password' => 'Your current password is not correct.']);
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

        return $this->respond($request, 'Your password was changed.', [], '#security');
    }

    /**
     * Update Attendee preferred display currency.
     * Invariant: Never changes currency charged for an order.
     */
    public function updateDisplayCurrency(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', \Illuminate\Validation\Rule::in(\App\Support\Currency::supportedCodes())],
        ]);

        $result = AccountSettingsService::updateAttendeeDisplayCurrency($request->user(), $validated['currency']);

        return $this->respond($request, 'Your display currency was saved.', $result, '#profile');
    }

    /**
     * Update Event Host event defaults and payment policies.
     */
    public function updateHostSettings(UpdateHostSettingsRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $tenantId = self::ownedOrganizerId($user);
        if ($tenantId === null) {
            abort(403, 'Only an owner of an organizer can change its event defaults and payment instructions.');
        }

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

        if (isset($validated['food_prep_buffer_percent'])) {
            HostOperationalRule::updateOrCreate(['organizer_id' => $tenantId], ['food_prep_buffer_percent' => (int) $validated['food_prep_buffer_percent']]);
        }

        AuditLogService::log(
            action: 'host_payment_policy_updated',
            targetEntity: 'host_payment_policies',
            targetId: (string) $policies->id,
            details: 'Host updated payment instructions and refund policy. Existing orders keep the terms they were placed under.',
            actor: $user
        );

        return $this->respond($request, 'Event defaults and payment settings were saved. Existing orders are not changed.', [
            'event_defaults' => $eventDefaults,
            'payment_policies' => $policies,
        ], '#host');
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
    /**
     * JSON for API clients; for the settings page, back to the section that was saved with a message.
     *
     * @param array<string, mixed> $payload
     */
    private function respond(Request $request, string $message, array $payload = [], string $anchor = ''): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message] + $payload);
        }

        return redirect(route('settings.show') . $anchor)->with('status', $message);
    }

    /** The organizer this host owns: the active tenant if they own it, otherwise their only owned organizer. */
    private static function ownedOrganizerId(\App\Models\User $user): ?string
    {
        $owned = OrganizerUser::where('user_id', $user->id)->where('is_active', true)
            ->where('role', TenantRole::EVENT_OWNER->value)->pluck('organizer_id');
        $tenantId = TenantContext::id();
        if ($tenantId !== null && $owned->contains($tenantId)) {
            return $tenantId;
        }

        return $owned->count() === 1 ? (string) $owned->first() : null;
    }
}
