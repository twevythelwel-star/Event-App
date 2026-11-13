<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostPaymentPolicy extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'host_payment_policies';

    protected $fillable = [
        'id',
        'organizer_id',
        'enable_cash',
        // Which payment methods this organizer offers (added with payment processing, migration 000021)
        'enable_card',
        'enable_cash_app_pay',
        'enable_paypal',
        'enable_zelle',
        'enable_cashapp',
        'enable_bank',
        'cash_overpayment_rule',
        'cash_underpayment_rule',
        'zelle_instructions',
        'cash_app_instructions',
        // Where customers send Zelle and Cash App payments (migration 2026_11_13_000001)
        'zelle_recipient',
        'cash_app_cashtag',
        // Retired: bank transfer is no longer offered. Kept so older rows stay readable.
        'bank_transfer_instructions',
        'require_transfer_reference',
        'require_sender_name',
        'require_receipt_snapshot',
        'partial_payments_enabled',
        'deposit_percentage',
        'auto_email_receipt',
        'auto_sms_receipt',
        'refund_policy_deadline_hours',
        'refund_policy_notes',
        'refund_policy_mode',
        'host_cancellation_policy',
        'postponement_policy',
        'weather_cancellation_policy',
        'food_order_cancellation_deadline_hours',
        'food_order_modification_deadline_hours',
        'non_refundable_ticket_categories',
    ];

    protected $casts = [
        'enable_cash' => 'boolean',
        'enable_card' => 'boolean',
        'enable_cash_app_pay' => 'boolean',
        'enable_paypal' => 'boolean',
        'enable_zelle' => 'boolean',
        'enable_cashapp' => 'boolean',
        'enable_bank' => 'boolean',
        'require_transfer_reference' => 'boolean',
        'require_sender_name' => 'boolean',
        'require_receipt_snapshot' => 'boolean',
        'partial_payments_enabled' => 'boolean',
        'deposit_percentage' => 'integer',
        'auto_email_receipt' => 'boolean',
        'auto_sms_receipt' => 'boolean',
        'refund_policy_deadline_hours' => 'integer',
        'food_order_cancellation_deadline_hours' => 'integer',
        'food_order_modification_deadline_hours' => 'integer',
        'non_refundable_ticket_categories' => 'array',
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    /** A $Cashtag: a letter first, then up to 19 letters, digits, hyphens or underscores. The leading $ is optional on input. */
    public const CASHTAG_PATTERN = '/^\$?[A-Za-z][A-Za-z0-9_-]{0,19}$/';

    /**
     * Validation for the Zelle and Cash App handles, shared by the settings page and the host API.
     *
     * @return array<string, list<mixed>>
     */
    public static function handleRules(bool $sometimes = false): array
    {
        $prefix = $sometimes ? ['sometimes'] : [];

        return [
            'cash_app_cashtag' => [...$prefix, 'nullable', 'string', 'max:21', 'regex:' . self::CASHTAG_PATTERN],
            'zelle_recipient' => [...$prefix, 'nullable', 'string', 'max:190', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== null && trim((string) $value) !== '' && !self::isZelleRecipient((string) $value)) {
                    $fail('Enter the email address or U.S. mobile number your Zelle is enrolled with.');
                }
            }],
        ];
    }

    /** @return array<string, string> */
    public static function handleMessages(): array
    {
        return ['cash_app_cashtag.regex' => 'Enter a $Cashtag: a letter, then up to 19 letters, numbers, hyphens or underscores.'];
    }

    public static function isZelleRecipient(string $value): bool
    {
        $value = trim($value);
        if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
            return true;
        }
        if (!preg_match('/^[0-9+()\s.-]+$/', $value)) {
            return false;
        }
        $digits = preg_replace('/\D/', '', $value);

        return strlen($digits) === 10 || (strlen($digits) === 11 && $digits[0] === '1');
    }

    /** Stored as "$Name" whether or not the host typed the $. */
    public function setCashAppCashtagAttribute(?string $value): void
    {
        $value = trim((string) $value);
        $this->attributes['cash_app_cashtag'] = $value === '' ? null : '$' . ltrim($value, '$');
    }

    /** Emails are stored lower-case; phone numbers as the host typed them. */
    public function setZelleRecipientAttribute(?string $value): void
    {
        $value = trim((string) $value);
        $this->attributes['zelle_recipient'] = $value === '' ? null
            : (str_contains($value, '@') ? strtolower($value) : $value);
    }

    /**
     * Check if payment method can be enabled based on host location eligibility.
     * Bank transfer is retired and can no longer be enabled anywhere.
     */
    public function canEnableMethod(string $methodId, string $location): bool
    {
        if ($methodId === 'cash') {
            return true;
        }

        if ($methodId === 'zelle' || $methodId === 'cashapp') {
            return strtoupper($location) === 'US';
        }

        return false;
    }
}
