<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One wallet provider operation and its outcome (see WalletPassManager). No secrets: an error code at most. */
class WalletPassEvent extends Model
{
    public const UPDATED_AT = null;

    public const SUCCESS = 'success';

    public const FAILURE = 'failure';

    protected $fillable = ['wallet_pass_id', 'provider', 'action', 'outcome', 'error_code', 'retryable'];

    protected $casts = ['retryable' => 'boolean', 'created_at' => 'datetime'];

    public function pass(): BelongsTo
    {
        return $this->belongsTo(WalletPass::class, 'wallet_pass_id');
    }
}
