<?php

namespace App\Models;

use App\Services\MediaStorageService;
use App\Support\ResponsiveImage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Someone on the event's bill: a DJ, performer, band, speaker or host, with the profile the host wrote. */
class EventPerformer extends Model
{
    use HasUuids;

    public const ROLES = ['dj' => 'DJ', 'performer' => 'Performer', 'band' => 'Band', 'speaker' => 'Guest speaker', 'host' => 'Host / MC'];

    protected $fillable = ['event_id', 'name', 'role', 'headline', 'bio', 'links', 'photo_path', 'photo_variants', 'photo_alt_text', 'sort_order'];

    protected $casts = ['links' => 'array', 'photo_variants' => 'array', 'sort_order' => 'integer'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(EventActivity::class, 'event_activity_performer', 'performer_id', 'activity_id')->orderBy('starts_at');
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst((string) $this->role);
    }

    /** The profile photo the host uploaded, or null (pages then show the performer's initials, not a stock photo). */
    public function photo(): ?ResponsiveImage
    {
        if (!MediaStorageService::isStoredPath($this->photo_path)) {
            return null;
        }

        return ResponsiveImage::forPerformer($this);
    }

    /** @return list<array{label: string, url: string}> the profile's links, safe to show (https only) */
    public function safeLinks(): array
    {
        return array_values(array_filter(array_map(
            fn ($link) => is_array($link) && isset($link['url']) && str_starts_with((string) $link['url'], 'https://')
                ? ['label' => (string) ($link['label'] ?? parse_url($link['url'], PHP_URL_HOST)), 'url' => (string) $link['url']] : null,
            (array) $this->links,
        )));
    }
}
