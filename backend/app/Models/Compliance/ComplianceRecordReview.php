<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One step in a record's review history: who did what, the status change, the comment and a snapshot. */
class ComplianceRecordReview extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['record_type', 'record_id', 'action', 'from_status', 'to_status', 'user_id', 'comment', 'snapshot'];

    protected $casts = ['snapshot' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
