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
 * The organizer's payment methods and transfer instructions, for the host API. Stored on the organizer's one
 * payment policy (host_payment_policies), the same row the settings page and checkout use.
 */
class HostPaymentSettingsController extends Controller
{
    private const SWITCHES = ['enable_card', 'enable_cash_app_pay', 'enable_paypal', 'enable_zelle', 'enable_cashapp', 'enable_bank', 'enable_cash'];

    public function index(Request $request): JsonResponse
    {
        $policy = HostPaymentPolicy::firstOrNew(['organizer_id' => $this->ownedOrganizerId($request)]);

        return response()->json([
            'settings' => $policy->only([...self::SWITCHES, 'zelle_instructions', 'cash_app_instructions', 'bank_transfer_instructions']),
            'platform_approvals' => PaymentMethods::approvals(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $organizerId = $this->ownedOrganizerId($request);
        $validated = $request->validate(array_fill_keys(self::SWITCHES, ['sometimes', 'boolean']) + [
            'zelle_instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'cash_app_instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'bank_transfer_instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

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
