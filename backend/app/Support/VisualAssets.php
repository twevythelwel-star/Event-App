<?php

namespace App\Support;

/**
 * Looks up graphics in the EventBite visual asset library (config/visual_assets.php), so pages ask for an asset
 * by purpose ("the hero for a music event", "the empty orders illustration") rather than by file path.
 */
final class VisualAssets
{
    /** @return array{url: string, alt: string} the category hero artwork for an event category */
    public static function heroFor(?string $category): array
    {
        $category = mb_strtolower((string) $category);
        $key = (string) config('visual_assets.default_hero', 'festival');
        foreach ((array) config('visual_assets.category_heroes', []) as $word => $hero) {
            if ($category !== '' && str_contains($category, $word)) {
                $key = $hero;
                break;
            }
        }

        return self::entry('heroes', $key) ?? self::entry('heroes', 'festival');
    }

    /** @return array{url: string, alt: string}|null a feature illustration, by name or by one of its aliases */
    public static function illustration(string $name): ?array
    {
        $name = (string) (config("visual_assets.illustration_aliases.{$name}") ?? $name);

        return self::entry('illustrations', $name);
    }

    /** @return array{url: string, alt: string}|null the artwork for a whole cuisine (e.g. 'caribbean', 'plant-based') */
    public static function cuisine(string $name): ?array
    {
        return self::entry('cuisines', $name);
    }

    /**
     * The cuisine artwork for a tradition, given its config/traditions.php key or its label ("Latin American").
     * Null for traditions the artwork would misrepresent, so the caller keeps its plain art.
     *
     * @return array{url: string, alt: string}|null
     */
    public static function cuisineForTradition(?string $tradition): ?array
    {
        if ($tradition === null || $tradition === '') {
            return null;
        }
        $key = $tradition;
        if (config("traditions.{$tradition}") === null) {
            foreach ((array) config('traditions', []) as $candidate => $entry) {
                if (mb_strtolower((string) ($entry['label'] ?? '')) === mb_strtolower($tradition)) {
                    $key = $candidate;
                    break;
                }
            }
        }
        $cuisine = config("visual_assets.tradition_cuisines.{$key}");

        return is_string($cuisine) ? self::cuisine($cuisine) : null;
    }

    /** A decorative background's URL, or null when there is none of that name. */
    public static function background(string $name): ?string
    {
        return self::entry('backgrounds', $name)['url'] ?? null;
    }

    /** The empty-state illustration's URL, or null when there is none of that name. */
    public static function emptyState(?string $name): ?string
    {
        return $name ? (self::entry('empty', $name)['url'] ?? null) : null;
    }

    /** @return array{url: string, alt: string}|null */
    private static function entry(string $group, string $name): ?array
    {
        $asset = config("visual_assets.{$group}.{$name}");
        if (!is_array($asset) || empty($asset['file'])) {
            return null;
        }

        return ['url' => asset($asset['file']), 'alt' => (string) ($asset['alt'] ?? '')];
    }
}
