<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An Apple Wallet pass issued to one ticket holder for one order. The pass shows the admission barcode only while
 * the order is valid for entry and still belongs to this holder (App\Services\Wallet\AppleWalletPassBuilder).
 */
class WalletPass extends Model
{
    use HasUuids;

    protected $fillable = ['order_id', 'user_id', 'pass_type_id', 'serial_number', 'authentication_token',
        'voided_at', 'void_reason', 'last_modified_at'];

    protected $hidden = ['authentication_token'];

    protected $casts = [
        'authentication_token' => 'encrypted',
        'voided_at' => 'datetime',
        'last_modified_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
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
