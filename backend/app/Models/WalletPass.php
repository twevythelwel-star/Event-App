<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A wallet pass (Apple, Google or Samsung: `provider`) issued to one ticket holder for one order. The pass shows the
 * admission barcode only while the order is valid for entry and still belongs to this holder
 * (App\Services\Wallet\WalletTicketState). Status and sync columns are kept by App\Services\Wallet\WalletPassManager.
 */
class WalletPass extends Model
{
    use HasUuids;

    public const PROVIDERS = ['apple', 'google', 'samsung'];

    public const STATUS_ISSUED = 'issued';

    public const STATUS_SAVED = 'saved';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['order_id', 'user_id', 'provider', 'status', 'pass_type_id', 'serial_number', 'external_id',
        'authentication_token', 'voided_at', 'void_reason', 'last_modified_at', 'saved_at', 'revoked_at',
        'sync_fingerprint', 'synced_event_hash', 'synced_token_version', 'last_synced_at', 'last_error', 'provider_meta'];

    protected $hidden = ['authentication_token'];

    protected $casts = [
        'authentication_token' => 'encrypted',
        'voided_at' => 'datetime',
        'last_modified_at' => 'datetime',
        'saved_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'synced_token_version' => 'integer',
        'provider_meta' => 'array',
    ];

    protected $attributes = ['provider' => 'apple', 'status' => self::STATUS_ISSUED];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSaved(): bool
    {
        return $this->saved_at !== null;
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(WalletDeviceRegistration::class);
    }

    /** Mark the pass changed: Last-Modified moves forward by at least a second, so devices never miss an update. */
    public function touchModified(): void
    {
        $now = now()->startOfSecond();
        $previous = $this->last_modified_at?->copy()->startOfSecond();
        $this->last_modified_at = $previous && $previous->greaterThanOrEqualTo($now) ? $previous->addSecond() : $now;
        $this->save();
    }

    public function updateTag(): string
    {
        return (string) ($this->last_modified_at ?? Carbon::createFromTimestamp(0))->getTimestamp();
    }

    public function matchesToken(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals((string) $this->authentication_token, $token);
    }
}
