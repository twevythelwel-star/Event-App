<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A photograph in the event photography library: what kind of event it shows (subject), where it came from, its
 * licence and consent records, its focal point and alt text, and its review. The file is in private storage; only
 * approved photos are served publicly, and they are always shown labelled as illustrative.
 */
class LibraryPhoto extends Model
{
    use SoftDeletes;

    public const STATUSES = ['pending_review' => 'Awaiting review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'retired' => 'Retired'];

    public const SOURCES = ['generated' => 'Generated image', 'licensed' => 'Licensed stock photograph', 'commissioned' => 'Commissioned photograph'];

    public const MODEL_RELEASES = ['on_file' => 'Model release on file', 'not_needed' => 'Not needed (no identifiable real people)', 'none' => 'None'];

    protected $fillable = ['subject', 'title', 'alt_text', 'path', 'mime', 'width', 'height', 'size_bytes', 'variants', 'focal_x', 'focal_y',
        'source', 'source_detail', 'source_url', 'licence', 'licence_url', 'identifiable_people', 'model_release', 'standards_checked',
        'status', 'uploaded_by', 'reviewed_by', 'reviewed_at', 'review_notes'];

    protected $casts = ['variants' => 'array', 'standards_checked' => 'array', 'identifiable_people' => 'boolean', 'reviewed_at' => 'datetime'];

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function subjectLabel(): string
    {
        return (string) (config("photo_library.subjects.{$this->subject}.label") ?? $this->subject);
    }

    public function url(array $query = []): string
    {
        return route('api.media.library-photo', ['photoId' => $this->id] + $query + ['v' => substr(sha1((string) $this->path), 0, 10)], false);
    }

    /** Whether people may use it: approved, and its rights cover identifiable people. */
    public function isUsable(): bool
    {
        return $this->status === 'approved' && !$this->trashed()
            && (!$this->identifiable_people || $this->model_release !== 'none' || $this->source === 'generated');
    }
}
