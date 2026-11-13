<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A device that added a Wallet pass and asked to be told when it changes (PassKit web service registration). */
class WalletDeviceRegistration extends Model
{
    protected $fillable = ['device_library_identifier', 'push_token', 'wallet_pass_id'];

    protected $hidden = ['push_token'];

    public function pass(): BelongsTo
    {
        return $this->belongsTo(WalletPass::class, 'wallet_pass_id');
    }
}
