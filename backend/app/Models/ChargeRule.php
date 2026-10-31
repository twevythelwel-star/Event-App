<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A configured fee, tax or discount (see App\Services\Pricing\ChargeCalculator).
 *
 * Scope: platform-wide (no organizer or event), one organizer's events, or one event; optionally only
 * events in one US state. Taxes here are whatever an administrator or host has configured for their
 * situation. The application does not know or verify what any jurisdiction actually requires.
 */
class ChargeRule extends Model
{
    use HasUuids;

    public const KINDS = ['platform_fee', 'host_fee', 'tax', 'discount'];
    public const METHODS = ['percent', 'fixed_per_order', 'fixed_per_item'];
    public const APPLIES_TO = ['all', 'tickets', 'food'];

    protected $fillable = [
        'organizer_id',
        'event_id',
        'kind',
        'label',
        'method',
        'rate_bp',
        'amount_minor',
        'currency',
        'applies_to',
        'state_code',
        'is_active',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'rate_bp' => 'integer',
        'amount_minor' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
