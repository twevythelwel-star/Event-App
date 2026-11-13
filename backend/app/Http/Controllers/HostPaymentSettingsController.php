<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\TenantRole;
use App\Models\HostPaymentPolicy;
use App\Models\OrganizerUser;
use App\Payments\PaymentMethods;
use App\Services\AuditLogService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The organizer's payment methods for the host API: cash in person, Zelle and Cash App, with the Zelle email or
 * phone and $Cashtag customers send to (plus optional extra instructions). Bank transfer is retired. Stored on the organizer's one
 * payment policy (host_payment_policies), the same row the settings page and checkout use.
 */
class HostPaymentSettingsController extends Controller
{
    private const SWITCHES = ['enable_card', 'enable_cash_app_pay', 'enable_paypal', 'enable_zelle', 'enable_cashapp', 'enable_cash'];

    private const DETAILS = ['zelle_recipient', 'zelle_instructions', 'cash_app_cashtag', 'cash_app_instructions'];

    public function index(Request $request): JsonResponse
    {
        $policy = HostPaymentPolicy::firstOrNew(['organizer_id' => $this->ownedOrganizerId($request)]);

        return response()->json([
            'settings' => $policy->only([...self::SWITCHES, ...self::DETAILS]),
            'platform_approvals' => PaymentMethods::approvals(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $organizerId = $this->ownedOrganizerId($request);
        $validated = $request->validate(array_fill_keys(self::SWITCHES, ['sometimes', 'boolean']) + HostPaymentPolicy::handleRules(sometimes: true) + [
            'zelle_instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'cash_app_instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // Bank transfer is no longer offered: it can't be switched back on or given new instructions.
            'enable_bank' => ['prohibited'],
            'bank_transfer_instructions' => ['prohibited'],
        ], HostPaymentPolicy::handleMessages() + [
            'enable_bank.prohibited' => 'Bank transfer is no longer offered. Use Zelle, Cash App or cash in person.',
            'bank_transfer_instructions.prohibited' => 'Bank transfer is no longer offered. Use Zelle, Cash App or cash in person.',
        ]);
        foreach (['enable_zelle' => 'zelle_recipient', 'enable_cashapp' => 'cash_app_cashtag'] as $switch => $handle) {
            $after = array_key_exists($handle, $validated) ? $validated[$handle] : HostPaymentPolicy::where('organizer_id', $organizerId)->value($handle);
            if (($validated[$switch] ?? false) && trim((string) $after) === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([$handle => 'Add your ' . PaymentMethods::handleLabel($switch === 'enable_zelle' ? 'zelle' : 'cashapp') . ' before turning this method on.']);
            }
        }

        $policy = HostPaymentPolicy::firstOrNew(['organizer_id' => $organizerId]);
        $policy->fill($validated)->save();

        AuditLogService::log(
            action: 'host_payment_policy_updated',
            targetEntity: 'host_payment_policies',
            targetId: (string) $policy->id,
            details: 'Payment methods updated through the host API: ' . implode(', ', array_keys($validated)) . '.',
            actor: $request->user(),
        );

        return response()->json(['success' => true, 'message' => 'Payment settings updated.', 'settings' => $policy->fresh()]);
    }

    /** Only an owner of the organizer changes how it is paid. */
    private function ownedOrganizerId(Request $request): string
    {
        $user = $request->user();
        abort_unless($user?->hasRole(AccountType::EVENT_HOST), 403);

        $owned = OrganizerUser::where('user_id', $user->id)->where('is_active', true)
            ->where('role', TenantRole::EVENT_OWNER->value)->pluck('organizer_id');
        $tenant = TenantContext::id();
        if ($tenant !== null && $owned->contains($tenant)) {
            return $tenant;
        }
        abort_unless($owned->count() === 1, 403, 'Choose the organizer whose payment settings you are changing.');

        return (string) $owned->first();
    }
}
