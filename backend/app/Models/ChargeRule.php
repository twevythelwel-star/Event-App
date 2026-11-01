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

    /** Who the charge is for, in customer-facing words. */
    public function kindLabel(): string
    {
        return match ($this->kind) {
            'platform_fee' => 'EventBite fee',
            'host_fee' => 'Organizer fee',
            'tax' => 'Tax',
            'discount' => 'Discount',
            default => ucfirst((string) $this->kind),
        };
    }

    /** The rate or amount and what it applies to, e.g. "7.25% of food" or "$0.99 per ticket". */
    public function describe(): string
    {
        $target = ['all' => 'tickets and food', 'tickets' => 'tickets', 'food' => 'food'][$this->applies_to] ?? 'the order';
        $unit = ['all' => 'item', 'tickets' => 'ticket', 'food' => 'food item'][$this->applies_to] ?? 'item';

        return match ($this->method) {
            'percent' => intdiv((int) $this->rate_bp, 100) . '.' . str_pad((string) ((int) $this->rate_bp % 100), 2, '0', STR_PAD_LEFT) . "% of {$target}",
            'fixed_per_order' => \App\Support\Money::formatMinor((int) $this->amount_minor, (string) $this->currency) . ' per order',
            'fixed_per_item' => \App\Support\Money::formatMinor((int) $this->amount_minor, (string) $this->currency) . " per {$unit}",
            default => (string) $this->method,
        };
    }
}
