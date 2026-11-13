<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\LibraryPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The event photography library.
 *
 * Which photo an event may show, in order:
 *   1. the host's own flyer (and gallery photos), always;
 *   2. otherwise, an approved library photo of the kind of event the host said this is (Event::photo_subject), or,
 *      when they did not say, of the kind their chosen category stands for (subjectFor()), labelled
 *      "Illustrative photo";
 *   3. otherwise, artwork drawn from the event's own details (GeneratedFlyer).
 * A library photo is never chosen from an event's title, and a declared kind is never overridden by the category,
 * so a concert photo cannot reach a picnic.
 * Events of the same kind are spread across that kind's photos, so neighbouring cards differ when the library has
 * several photos of a kind.
 *
 * Bound as a scoped singleton: the approved photos are read once per request.
 */
final class PhotoLibrary
{
    /** @var Collection<string, Collection<int, LibraryPhoto>>|null approved, usable photos by subject */
    private ?Collection $approved = null;

    /** @return array<string, array{group: string, label: string, brief: string}> */
    public static function subjects(): array
    {
        return (array) config('photo_library.subjects', []);
    }

    /** @return array<string, array<string, string>> subject keys and labels, by group label (for select inputs) */
    public static function subjectOptions(): array
    {
        $options = [];
        foreach (self::subjects() as $key => $subject) {
            $options[config("photo_library.groups.{$subject['group']}", $subject['group'])][$key] = $subject['label'];
        }

        return $options;
    }

    public static function isSubject(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::subjects());
    }

    /**
     * The kind of event to illustrate: the kind the host declared, or, when they declared none, the kind their
     * chosen category stands for (config photo_library.category_subjects). Null when neither names a kind.
     * A declared kind is never second-guessed by the category, and the title is never read.
     */
    public static function subjectFor(Event $event): ?string
    {
        $declared = (string) ($event->photo_subject ?? '');
        if (self::isSubject($declared)) {
            return $declared;
        }
        $category = ' ' . trim((string) preg_replace('/[^a-z]+/', ' ', mb_strtolower((string) ($event->category ?? '')))) . ' ';
        if (trim($category) === '') {
            return null;
        }
        foreach ((array) config('photo_library.category_subjects', []) as $words => $subject) {
            if (str_contains($category, ' ' . $words . ' ') && self::isSubject($subject)) {
                return $subject;
            }
        }

        return null;
    }

    /** An approved photo of the event's own kind (declared, or from its category), or null (no kind, or none approved yet). */
    public function forEvent(Event $event): ?LibraryPhoto
    {
        $subject = (string) self::subjectFor($event);
        if ($subject === '') {
            return null;
        }
        $photos = $this->approved()->get($subject);
        if ($photos === null || $photos->isEmpty()) {
            return null;
        }

        // Stable for each event (the same photo on every page), and spread across events of the same kind.
        return $photos->values()->get(crc32((string) $event->id) % $photos->count());
    }

    /** @return Collection<int, LibraryPhoto> approved photos of a kind (for collections and category cards) */
    public function forSubject(string $subject): Collection
    {
        return $this->approved()->get($subject, collect());
    }

    /** Store an uploaded photo (validated, re-encoded and kept privately) with its sizes, awaiting review. */
    public function store(UploadedFile $file, array $data, int $uploaderId): LibraryPhoto
    {
        $media = app(MediaStorageService::class);
        $stored = $media->storeImage($file, 'platform', 'library');
        $minW = (int) config('photo_library.min_width', 1600);
        $minH = (int) config('photo_library.min_height', 1000);
        if ($stored['width'] < $minW || $stored['height'] < $minH) {
            $media->delete($stored['path']);
            throw new \InvalidArgumentException("Library photos must be at least {$minW}x{$minH}px; this one is {$stored['width']}x{$stored['height']}px.");
        }

        try {
            return DB::transaction(function () use ($media, $stored, $data, $uploaderId) {
                $variants = $media->createVariants($stored['path'], 'platform', 'library');

                return LibraryPhoto::create([
                    'subject' => $data['subject'], 'title' => $data['title'], 'alt_text' => $data['alt_text'],
                    'path' => $stored['path'], 'mime' => $stored['mime'], 'width' => $stored['width'], 'height' => $stored['height'],
                    'size_bytes' => $stored['size'], 'variants' => $variants,
                    'focal_x' => (int) ($data['focal_x'] ?? 50), 'focal_y' => (int) ($data['focal_y'] ?? 50),
                    'source' => $data['source'], 'source_detail' => $data['source_detail'] ?? null, 'source_url' => $data['source_url'] ?? null,
                    'licence' => $data['licence'], 'licence_url' => $data['licence_url'] ?? null,
                    'identifiable_people' => (bool) ($data['identifiable_people'] ?? false), 'model_release' => $data['model_release'],
                    'status' => 'pending_review', 'uploaded_by' => $uploaderId,
                ]);
            });
        } catch (\Throwable $e) {
            $media->delete($stored['path']);
            throw $e;
        }
    }

    /** @return Collection<string, Collection<int, LibraryPhoto>> */
    private function approved(): Collection
    {
        if ($this->approved === null) {
            try {
                $this->approved = LibraryPhoto::approved()->orderBy('id')->get()->filter->isUsable()->groupBy('subject');
            } catch (\Throwable) {
                $this->approved = collect(); // table not migrated (isolated runner)
            }
        }

        return $this->approved;
    }
}
