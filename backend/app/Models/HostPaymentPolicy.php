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
        'zelle_instructions',
        'cash_app_instructions',
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
    ];

    protected $casts = [
        'enable_cash' => 'boolean',
        'require_transfer_reference' => 'boolean',
        'require_sender_name' => 'boolean',
        'require_receipt_snapshot' => 'boolean',
        'partial_payments_enabled' => 'boolean',
        'deposit_percentage' => 'integer',
        'auto_email_receipt' => 'boolean',
        'auto_sms_receipt' => 'boolean',
        'refund_policy_deadline_hours' => 'integer',
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    /**
     * Check if payment method can be enabled based on host location eligibility.
     */
    public function canEnableMethod(string $methodId, string $location): bool
    {
        if ($methodId === 'cash' || $methodId === 'bank' || $methodId === 'bank_transfer') {
            return true;
        }

        if ($methodId === 'zelle' || $methodId === 'cashapp') {
            return strtoupper($location) === 'US';
        }

        return false;
    }
}
