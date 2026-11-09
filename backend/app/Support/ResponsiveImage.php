<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventMedia;
use App\Models\MenuItem;
use App\Services\MediaStorageService;

/**
 * One image as pages need it: a fallback URL, srcsets per format (AVIF, WebP), its intrinsic size, alt text and
 * focal point. Built from the stored references on events, menu items and event media, so every page renders
 * uploaded images the same way through <x-responsive-image>.
 *
 * Uploaded files are always served through MediaController, which checks who may see them; the URLs here are
 * relative and versioned by the stored path, so a replaced image is never served from a stale cache.
 */
final class ResponsiveImage
{
    /**
     * @param array<string, string> $sources MIME type => srcset ("url 400w, url 800w, …")
     */
    public function __construct(
        public readonly string $src,
        public readonly array $sources = [],
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly string $alt = '',
        public readonly int $focalX = 50,
        public readonly int $focalY = 50,
        public readonly bool $uploaded = false,
        public readonly bool $illustrative = false,
        /** Artwork EventBite drew from the record's own details because no image was uploaded. */
        public readonly bool $generated = false,
        /** Shown instead if the image cannot be loaded (e.g. the event's generated artwork for a flyer). */
        public readonly ?string $fallback = null,
        /** What the picture is, shown on it when it is not the record's own image, e.g. "Illustrative photo". */
        public readonly ?string $label = null,
    ) {}

    /** The CSS object-position for the focal point. */
    public function objectPosition(): string
    {
        return "{$this->focalX}% {$this->focalY}%";
    }

    /** The event's flyer as uploaded (with its variants), a bundled image, or the generated artwork. */
    public static function forEventCover(Event $event): self
    {
        $alt = (string) ($event->flyer_alt_text ?: ($event->title ? $event->title . ' event flyer' : 'Event flyer'));
        $x = (int) ($event->flyer_position_x ?? 50);
        $y = (int) ($event->flyer_position_y ?? 50);
        $path = (string) ($event->flyer_path ?? '');

        if (MediaStorageService::isStoredPath($path) && !$event->flyerIsHidden()) {
            $url = fn (array $query) => route('api.media.flyer', ['eventId' => $event->id] + $query, false);

            $image = self::fromVariants($event->flyer_variants, $url, $path, $alt, $x, $y,
                $event->flyer_width, $event->flyer_height);

            // If the stored flyer cannot be loaded, the event's own generated artwork shows, never a broken image.
            return new self($image->src, $image->sources, $image->width, $image->height, $image->alt, $x, $y,
                uploaded: true, fallback: route('events.generated-flyer', $event->id, false));
        }
        // A link the host gave to their own artwork. Bundled stock photos are never an event's cover (ImageSubjects).
        if (str_starts_with($path, 'https://')) {
            return new self($path, alt: $alt, focalX: $x, focalY: $y);
        }

        // No flyer: an approved library photograph of the kind of event the host said this is, labelled as illustrative.
        if ($photo = app(\App\Services\PhotoLibrary::class)->forEvent($event)) {
            return self::forLibraryPhoto($photo, route('events.generated-flyer', $event->id, false),
                'Illustrative photo of a ' . strtolower($photo->subjectLabel()) . ', not of this event: ' . ($event->title ?: 'this event') . ' has no flyer yet');
        }

        // Otherwise: artwork drawn from the event's own details, said to be artwork rather than a photo.
        return new self($event->display_image_url, width: 1200, height: 750,
            alt: 'Artwork made from the event details for ' . ($event->title ?: 'this event') . ' (no flyer uploaded)', generated: true);
    }

    /** A library photograph with its sizes and focal point, labelled as illustrative wherever it is shown. */
    public static function forLibraryPhoto(\App\Models\LibraryPhoto $photo, ?string $fallback = null, ?string $alt = null): self
    {
        $url = fn (array $query) => route('api.media.library-photo', ['photoId' => $photo->id] + $query, false);
        $image = self::fromVariants($photo->variants, $url, (string) $photo->path, $alt ?? (string) $photo->alt_text,
            (int) $photo->focal_x, (int) $photo->focal_y, $photo->width, $photo->height);

        return new self($image->src, $image->sources, $image->width, $image->height, $image->alt, $image->focalX, $image->focalY,
            illustrative: true, fallback: $fallback, label: 'Illustrative photo');
    }

    /** A menu item's photo, or null when it has none to show (no photo, or hidden by an administrator). */
    public static function forMenuItem(MenuItem $item): ?self
    {
        if ($item->imageIsHidden()) {
            return null;
        }
        [$x, $y] = self::position($item->image_position);
        $alt = (string) ($item->image_alt_text ?: ('Photo of ' . $item->name));

        if (MediaStorageService::isStoredPath($item->image_path ?? null)) {
            $url = fn (array $query) => route('api.media.menu-photo', ['itemId' => $item->id] + $query, false);

            return self::fromVariants($item->image_variants, $url, (string) $item->image_path, $alt, $x, $y,
                $item->image_width, $item->image_height, fallbackSrc: $item->display_image_url);
        }
        $src = $item->display_image_url;
        if ($src === null) {
            return null;
        }
        $illustrative = $item->photoIsIllustrative();
        // A catalogued sample: its optimized WebP/AVIF copies (images:optimize-samples) instead of the ~1 MB original.
        $sources = ImageSubjects::isBundled($src) ? ImageSubjects::optimizedSources($src) : [];
        [$w, $h] = ImageSubjects::isBundled($src) ? self::bundledSize($src) : [null, null];

        return new self($src, $sources, $w, $h, alt: $illustrative ? 'Illustrative photo' : $alt, focalX: $x, focalY: $y, illustrative: $illustrative);
    }

    public static function forPerformer(\App\Models\EventPerformer $performer): self
    {
        $url = fn (array $query) => route('api.media.performer-photo', ['performerId' => $performer->id] + $query, false);

        return self::fromVariants($performer->photo_variants, $url, (string) $performer->photo_path,
            (string) ($performer->photo_alt_text ?: 'Photo of ' . $performer->name), 50, 30, null, null);
    }

    public static function forSponsor(\App\Models\EventSponsor $sponsor): self
    {
        $url = fn (array $query) => route('api.media.sponsor-logo', ['sponsorId' => $sponsor->id] + $query, false);

        return self::fromVariants($sponsor->logo_variants, $url, (string) $sponsor->logo_path,
            (string) ($sponsor->logo_alt_text ?: $sponsor->name . ' logo'), 50, 50, null, null);
    }

    public static function forEventMedia(EventMedia $media): self
    {
        $url = fn (array $query) => route('api.media.event-media', ['mediaId' => $media->id] + $query, false);
        $variants = $media->variants;
        if (!$variants && $media->large_thumbnail_path) {
            // Uploaded before formats were recorded: the three WebP sizes only.
            $variants = array_filter([
                'small' => $media->small_thumbnail_path ? ['webp' => $media->small_thumbnail_path] : null,
                'medium' => $media->medium_thumbnail_path ? ['webp' => $media->medium_thumbnail_path] : null,
                'large' => ['webp' => $media->large_thumbnail_path],
            ]);
        }

        return self::fromVariants($variants, $url, (string) $media->original_path, (string) $media->alt_text,
            (int) $media->position_x, (int) $media->position_y, $media->width, $media->height);
    }

    /**
     * @param array<string, array{width?: int, height?: int, webp?: string, avif?: string}>|null $variants
     * @param callable(array<string, string>): string $url builds the media route for a query
     */
    private static function fromVariants(?array $variants, callable $url, string $originalPath, string $alt, int $x, int $y,
        ?int $width, ?int $height, ?string $fallbackSrc = null): self
    {
        $version = fn (string $path) => substr(sha1($path), 0, 10);
        $original = $url(['v' => $version($originalPath)]);
        if (!$variants) {
            return new self($fallbackSrc ?? $original, width: $width, height: $height, alt: $alt, focalX: $x, focalY: $y, uploaded: true);
        }

        // The longest edge of each size, so srcset can say how wide each file is.
        $sources = [];
        foreach (['avif' => 'image/avif', 'webp' => 'image/webp'] as $format => $mime) {
            $entries = [];
            foreach (MediaStorageService::SIZES as $size => $edge) {
                $path = $variants[$size][$format] ?? null;
                if ($path === null) {
                    continue;
                }
                $w = (int) ($variants[$size]['width'] ?? self::scaledWidth($width, $height, $edge));
                $entries[] = $url(['variant' => $size, 'format' => $format, 'v' => $version($path)]) . " {$w}w";
            }
            if ($entries !== []) {
                $sources[$mime] = implode(', ', $entries);
            }
        }
        $large = $variants['large'] ?? $variants['medium'] ?? null;
        $src = isset($large['webp'])
            ? $url(['variant' => isset($variants['large']) ? 'large' : 'medium', 'format' => 'webp', 'v' => $version($large['webp'])])
            : $original;

        return new self($src, $sources, (int) ($large['width'] ?? $width) ?: $width, (int) ($large['height'] ?? $height) ?: $height,
            $alt, $x, $y, uploaded: true);
    }

    /** @return array{0: ?int, 1: ?int} the pixel size of a bundled image, read once per request */
    private static function bundledSize(string $url): array
    {
        static $sizes = [];
        $file = public_path(ltrim((string) parse_url($url, PHP_URL_PATH), '/'));
        if (!array_key_exists($file, $sizes)) {
            $info = is_file($file) ? @getimagesize($file) : false;
            $sizes[$file] = $info ? [(int) $info[0], (int) $info[1]] : [null, null];
        }

        return $sizes[$file];
    }

    private static function scaledWidth(?int $width, ?int $height, int $edge): int
    {
        if (!$width || !$height) {
            return $edge;
        }

        return (int) round($width * min(1, $edge / max($width, $height)));
    }

    /** @return array{0: int, 1: int} a "x% y%" focal point, or the centre */
    private static function position(?string $position): array
    {
        if (is_string($position) && preg_match('/^(\d{1,3})% (\d{1,3})%$/', $position, $m)) {
            return [min(100, (int) $m[1]), min(100, (int) $m[2])];
        }

        return [50, 50];
    }
}
