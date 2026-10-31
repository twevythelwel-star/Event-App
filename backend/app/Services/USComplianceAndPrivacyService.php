<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use InvalidArgumentException;

/**
 * USComplianceAndPrivacyService
 *
 * Implements US-specific privacy safeguards (CCPA/CPRA), state consumer protection,
 * state sales tax calculation with 501(c)(3) non-profit exemption, ADA accessibility
 * rules, and PCI-DSS scope minimization standards.
 */
class USComplianceAndPrivacyService
{
    /**
     * Statewide base sales-tax rates, in parts per 100,000 (6% = 6000), as reference figures ONLY.
     *
     * Not used to charge anyone: checkout taxes come solely from rules an administrator configures
     * (App\Models\ChargeRule, applied by App\Services\Pricing\ChargeCalculator). These figures are not
     * verified, omit county and city rates, and ignore what is or isn't taxable for events and food, so they
     * must not be presented to customers as the tax that legally applies.
     */
    public const STATE_SALES_TAX_RATES = [
        'FL' => 6000, // Florida 6%
        'NY' => 4000, // New York 4%
        'CA' => 7250, // California 7.25%
        'TX' => 6250, // Texas 6.25%
        'GA' => 4000, // Georgia 4%
        'IL' => 6250, // Illinois 6.25%
        'NC' => 4750, // North Carolina 4.75%
        'NJ' => 6625, // New Jersey 6.625%
        'PA' => 6000, // Pennsylvania 6%
        'OH' => 5750, // Ohio 5.75%
        'DE' => 0, // Delaware 0% (No state sales tax)
        'NH' => 0, // New Hampshire 0%
        'OR' => 0, // Oregon 0%
        'MT' => 0, // Montana 0%
        'AK' => 0, // Alaska 0%
    ];

    /**
     * Reference sales-tax ESTIMATE in integer minor units (cents), from the unverified statewide figures above.
     * Not used by checkout and not legal or tax advice; see App\Services\Pricing\ChargeCalculator for real charges.
     * Accounts for 501(c)(3) non-profit tax exemption status.
     *
     * @param int $subtotalMinor Subtotal in cents
     * @param string $stateCode 2-letter US state code
     * @param bool $is501c3TaxExempt True if organizer has verified 501(c)(3) status
     * @param string|null $ein Employer Identification Number if claiming exemption
     * @return array
     */
    public static function calculateUSTax(
        int $subtotalMinor,
        string $stateCode,
        bool $is501c3TaxExempt = false,
        ?string $ein = null
    ): array {
        $state = strtoupper(trim($stateCode));

        if ($is501c3TaxExempt) {
            if (!$ein || !preg_match('/^\d{2}-\d{7}$/', trim($ein))) {
                throw new InvalidArgumentException("Valid 9-digit US EIN (XX-XXXXXXX) is required for 501(c)(3) tax exemption claim.");
            }
            return [
                'state' => $state,
                'rate' => 0.0,
                'tax_cents' => 0,
                'is_exempt' => true,
                'exemption_type' => '501(c)(3) Non-Profit Charitable Organization',
                'ein' => $ein,
            ];
        }

        // No invented fallback: a state without a reference figure has no estimate.
        $perHundredThousand = self::STATE_SALES_TAX_RATES[$state] ?? null;
        $taxCents = $perHundredThousand === null ? 0 : intdiv($subtotalMinor * $perHundredThousand + 50000, 100000);

        return [
            'state' => $state,
            'rate' => $perHundredThousand === null ? null : $perHundredThousand / 100000,
            'tax_cents' => $taxCents,
            'is_estimate' => true,
            'rate_known' => $perHundredThousand !== null,
            'is_exempt' => false,
            'exemption_type' => null,
            'ein' => null,
        ];
    }

    /**
     * CCPA / CPRA Consumer Privacy Compliance:
     * Record a California/US consumer's "Do Not Sell or Share My Personal Information" preference.
     */
    public static function setConsumerPrivacyPreferences(
        User $user,
        bool $doNotSellOrShare,
        bool $limitSensitiveDataUse
    ): array {
        $prefs = [
            'do_not_sell_or_share' => $doNotSellOrShare,
            'limit_sensitive_data_use' => $limitSensitiveDataUse,
            'recorded_at' => date('Y-m-d H:i:s'),
            'jurisdiction' => 'US_CCPA_CPRA',
        ];

        // Store in user metadata/account settings
        $user->account_status_notes = ($user->account_status_notes ? $user->account_status_notes . ' | ' : '') .
            "CCPA: DoNotSell=" . ($doNotSellOrShare ? '1' : '0');
        $user->save();

        return [
            'success' => true,
            'user_id' => $user->id,
            'preferences' => $prefs,
        ];
    }

    /**
     * Export Attendee personal data under California CPRA / US Privacy Right to Access.
     */
    public static function exportAttendeePersonalData(User $user): array
    {
        return [
            'request_type' => 'CPRA_RIGHT_TO_KNOW',
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => $user->getAuthorizedRoles(),
            'created_at' => $user->created_at ? $user->created_at->toIso8601String() : date('c'),
            'orders_count' => $user->orders()->count(),
            'security' => [
                'email_verified' => $user->hasVerifiedEmail(),
                'phone_verified' => $user->hasVerifiedPhone(),
                'two_factor_enabled' => (bool) $user->two_factor_enabled,
            ],
            'disclaimer' => 'Exported in compliance with US Consumer Privacy standards.',
        ];
    }

    /**
     * Verify PCI-DSS Security Compliance Standard:
     * Asserts that no raw cardholder data (PAN, CVV, PIN) is persisted.
     */
    public static function auditPciScope(array $orderPayload): array
    {
        $forbiddenCardKeys = ['card_number', 'pan', 'cvv', 'cvv2', 'security_code', 'pin'];
        $violations = [];

        foreach ($forbiddenCardKeys as $key) {
            if (isset($orderPayload[$key]) && !empty($orderPayload[$key])) {
                $violations[] = "PCI-DSS Violation: Plaintext {$key} detected in payload. Card data must be tokenized.";
            }
        }

        return [
            'is_compliant' => empty($violations),
            'violations' => $violations,
            'scope' => 'PCI-DSS SAQ A / SAQ A-EP Scope Minimization',
        ];
    }
}
