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

    /** @return array{url: string, alt: string}|null a feature illustration */
    public static function illustration(string $name): ?array
    {
        return self::entry('illustrations', $name);
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
