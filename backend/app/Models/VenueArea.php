<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Something drawn on the venue plan: a ticketed area, a table, rows of seats, or a service or restricted area. */
class VenueArea extends Model
{
    use HasUuids;

    public const KINDS = [
        'general_admission' => 'General admission',
        'vip' => 'VIP section',
        'table' => 'Reserved table',
        'seat_rows' => 'Rows of seats',
        'food_station' => 'Food station',
        'vendor_booth' => 'Vendor booth',
        'stage' => 'Stage',
        'restricted' => 'Restricted area',
    ];

    /** Areas that hold individual, selectable seats. */
    public const SEATED = ['table', 'seat_rows'];

    /** Areas that sell admission (with a ticket type). */
    public const TICKETED = ['general_admission', 'vip', 'table', 'seat_rows'];

    protected $fillable = ['event_id', 'kind', 'label', 'x', 'y', 'width', 'height', 'ticket_tier_id', 'capacity', 'rows',
        'seats_per_row', 'seats_per_table', 'is_accessible', 'notes', 'sort_order'];

    protected $casts = ['x' => 'integer', 'y' => 'integer', 'width' => 'integer', 'height' => 'integer', 'capacity' => 'integer',
        'rows' => 'integer', 'seats_per_row' => 'integer', 'seats_per_table' => 'integer', 'is_accessible' => 'boolean', 'sort_order' => 'integer'];

    public function seats(): HasMany
    {
        return $this->hasMany(VenueSeat::class, 'area_id')->orderBy('row_label')->orderBy('number');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst((string) $this->kind);
    }

    public function isSeated(): bool
    {
        return in_array($this->kind, self::SEATED, true);
    }
}
