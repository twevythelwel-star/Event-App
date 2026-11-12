<?php

namespace App\Support;

use App\Models\Event;
use App\Models\MenuItem;

/**
 * The link between an image and what it shows, so an image only appears where it is true.
 *
 * - A host's own upload is the host's statement about their dish or event, and is shown as such.
 * - A bundled sample photo shows one particular dish (config/visual_assets.php `sample_photos`, each with the
 *   dish names it may stand for). It appears only on a menu item whose name is that dish, and is always labelled
 *   "Illustrative photo". A jerk chicken photo never appears on sushi, lobster or a drink.
 * - Bundled photos are never an event's cover: an event is shown by its own flyer, or by artwork made from its own
 *   details, never by stock photography of some other event.
 * - Anything uncatalogued (an unknown /images/ file) is not shown at all; the clearly labelled fallback is.
 */
final class ImageSubjects
{
    /** Sizes of the optimized copies of sample photos (longest edge; the originals are 1200 px wide). */
    public const SAMPLE_SIZES = ['small' => 400, 'medium' => 800, 'large' => 1200];

    /** Where `php artisan images:optimize-samples` writes a sample's optimized copy (relative to public/). */
    public static function optimizedPath(string $file, string $size, string $format): string
    {
        return 'images/optimized/' . pathinfo($file, PATHINFO_FILENAME) . "-{$size}.{$format}";
    }

    /**
     * The optimized copies of a catalogued sample that exist on disk, as srcsets by MIME type.
     *
     * @return array<string, string>
     */
    public static function optimizedSources(string $url): array
    {
        $sample = self::sample($url);
        if ($sample === null) {
            return [];
        }
        $sources = [];
        foreach (['avif' => 'image/avif', 'webp' => 'image/webp'] as $format => $mime) {
            $entries = [];
            foreach (self::SAMPLE_SIZES as $size => $edge) {
                $path = self::optimizedPath($sample['file'], $size, $format);
                if (is_file(public_path($path))) {
                    $entries[] = '/' . $path . " {$edge}w";
                }
            }
            if ($entries !== []) {
                $sources[$mime] = implode(', ', $entries);
            }
        }

        return $sources;
    }
    /**
     * The catalogue entry for a bundled sample photo, or null when the path is not a catalogued sample.
     *
     * @return array{file: string, dish: string, matches: list<string>, cuisine: string}|null
     */
    public static function sample(?string $url): ?array
    {
        $file = ltrim((string) parse_url((string) $url, PHP_URL_PATH), '/');
        if ($file === '' || !str_starts_with($file, 'images/')) {
            return null;
        }
        foreach ((array) config('visual_assets.sample_photos', []) as $cuisine => $photos) {
            foreach ((array) $photos as $photo) {
                if (($photo['file'] ?? null) === $file) {
                    $matches = (array) ($photo['matches'] ?? [$photo['dish'] ?? '']);
                    return array_merge($photo, ['cuisine' => (string) $cuisine,
                        'matches' => array_values(array_unique(array_map(static fn ($dish) => mb_strtolower((string) $dish), $matches)))]);
                }
            }
        }

        return null;
    }

    /** Whether a bundled image may illustrate this menu item: it is a catalogued sample of the dish the item names. */
    public static function sampleFitsMenuItem(?string $url, MenuItem $item): bool
    {
        $sample = self::sample($url);
        if ($sample === null) {
            return false;
        }

        return self::namesDish((string) $item->name, $sample['matches']);
    }

    /** Whether a bundled image path is a sample photo at all (any /images/… file, catalogued or not). */
    public static function isBundled(?string $url): bool
    {
        return str_starts_with((string) parse_url((string) $url, PHP_URL_PATH), '/images/');
    }

    /** Bundled photographs are never shown as an event's cover; only the host's own artwork or generated artwork is. */
    public static function eventMayUse(?string $path, Event $event): bool
    {
        return !self::isBundled($path);
    }

    /**
     * The clearly labelled fallback for a menu item without a usable photo: an icon and words for its kind of food,
     * so guests see "Seafood · No photo yet" rather than a stock picture of something else.
     *
     * @return array{icon: string, label: string}
     */
    public static function menuFallback(MenuItem $item): array
    {
        $category = (string) $item->category;
        $icon = match (true) {
            $item->isPackage() => 'package',
            in_array($category, ['Seafood'], true) => 'fish',
            in_array($category, ['Vegetarian', 'Vegan'], true) => 'leaf',
            in_array($category, ['Rice and Grains'], true) => 'wheat',
            in_array($category, ['Desserts'], true) => 'cake',
            in_array($category, ['Drinks'], true) => 'cup',
            in_array($category, ['Sauces and Condiments'], true) => 'flame',
            default => 'utensils',
        };

        return ['icon' => $icon, 'label' => $item->isPackage() ? 'Meal package' : ($category !== '' ? $category : 'Food')];
    }

    /** @param list<string> $dishes */
    private static function namesDish(string $name, array $dishes): bool
    {
        $name = ' ' . preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($name)) . ' ';
        foreach ($dishes as $dish) {
            $dish = trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower((string) $dish)));
            if ($dish !== '' && str_contains($name, ' ' . $dish . ' ')) {
                return true;
            }
        }

        return false;
    }
}
