<?php

namespace App\Models;

use App\Services\CredentialMaskingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountSetting extends Model
{
    use HasFactory;

    protected $table = 'account_settings';

    protected $fillable = [
        'user_id',
        'category',
        'setting_key',
        'setting_value',
        'is_sensitive',
    ];

    protected $casts = [
        'is_sensitive' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get setting value, masking sensitive content if requested.
     */
    public function getSafeValue(bool $maskSensitive = true): ?string
    {
        if ($this->is_sensitive && $maskSensitive && !empty($this->setting_value)) {
            return CredentialMaskingService::maskSecret($this->setting_value);
        }

        return $this->setting_value;
    }
}
