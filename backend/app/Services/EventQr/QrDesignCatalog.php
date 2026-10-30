<?php

declare(strict_types=1);

namespace App\Services\EventQr;

/**
 * The approved visual designs for event QR codes, and the rules every design must pass.
 *
 * A design is never built from unrestricted random values. Each preset lists the module shapes, finder
 * (corner-marker) shapes, colour pairs, borders and options it may use, and random selection happens only
 * within those whitelists. Every design is then checked by safe(): if anything would weaken scanning it is
 * replaced by the conventional fallback design.
 *
 * Scanning rules enforced here (the renderer and the decode check enforce the rest):
 * - Normal polarity: modules are darker than the plate behind them. Light-on-dark ("inverted") codes look
 *   striking but are not read by every scanner, so dark styles put their darkness in the frame instead.
 * - Contrast of at least 7:1 between modules (and corner markers) and the plate.
 * - Dot-shaped modules need error correction Q or H; a centre logo needs H.
 */
final class QrDesignCatalog
{
    public const VERSION = 1;

    public const MODULE_SHAPES = ['square', 'rounded', 'dot'];
    public const FINDER_SHAPES = ['square', 'rounded', 'circle'];
    public const BORDERS = ['none', 'line', 'double', 'dashed', 'corners', 'zigzag'];
    public const LOGOS = ['none', 'bite'];
    public const ECC_LEVELS = ['M', 'Q', 'H'];

    /** Hosts may choose these; "random" picks among all of them. Labels are shown in the host screen. */
    public const PRESETS = [
        'geometric' => 'Geometric',
        'circular' => 'Circular',
        'neon' => 'Neon',
        'minimal' => 'Premium Minimal',
        'festival' => 'Festival',
        'editorial' => 'Editorial',
        'custom_theme' => 'Custom Event Theme',
        'random_mix' => 'Random Combination',
    ];

    private const MIN_CONTRAST = 7.0;

    // Brand palette (EventBite tokens and logo concepts): navy canvas, electric lime, cyan and coral accents.
    private const NAVY = '#07111F';
    private const INK = '#0B0F14';
    private const LIME = '#C7FF45';
    private const CYAN = '#7FF0F7';
    private const CORAL = '#FF6B5A';

    /**
     * Whitelisted options per preset. Colour pairs are [module colour, plate colour]; every pair is checked
     * by safe() anyway, so a mistake here falls back rather than shipping an unreadable code.
     *
     * @var array<string, array<string, list<mixed>>>
     */
    private const OPTIONS = [
        'geometric' => [
            'module' => ['square', 'rounded'],
            'finder' => ['rounded', 'square'],
            'colors' => [[self::NAVY, self::LIME], [self::NAVY, '#FFFFFF'], ['#0B2A6B', '#FFFFFF']],
            'finder_accent' => [null, '#3B0F3F'],
            'frame' => [self::NAVY],
            'border' => ['corners', 'line'],
            'border_color' => [self::LIME],
            'logo' => ['none'],
            'label' => ['Scan for the event'],
        ],
        'circular' => [
            'module' => ['dot'],
            'finder' => ['circle', 'rounded'],
            'colors' => [[self::NAVY, '#FFFFFF'], ['#3B0F3F', '#FFF8E7'], ['#0F3D3E', '#F1FBF6']],
            'finder_accent' => ['#5A0F1C', '#0B2A6B', null],
            'frame' => ['#FFFFFF', '#FFF8E7'],
            'border' => ['line', 'none'],
            'border_color' => [self::NAVY, self::CORAL],
            'logo' => ['none'],
            'label' => [null],
        ],
        'neon' => [
            'module' => ['rounded', 'square'],
            'finder' => ['rounded'],
            'colors' => [[self::INK, self::CYAN], [self::INK, self::LIME]],
            'finder_accent' => [null],
            'frame' => ['#05070D'],
            'border' => ['line', 'double'],
            'border_color' => [self::LIME, self::CYAN],
            'logo' => ['none', 'bite'],
            'label' => ['Scan me'],
        ],
        'minimal' => [
            'module' => ['square'],
            'finder' => ['square'],
            'colors' => [[self::INK, '#FFFFFF']],
            'finder_accent' => [null],
            'frame' => ['#FFFFFF', '#F6F7F9'],
            'border' => ['line', 'none'],
            'border_color' => ['#D5D9E0'],
            'logo' => ['none'],
            'label' => ['EventBite'],
        ],
        'festival' => [
            'module' => ['rounded', 'dot'],
            'finder' => ['rounded', 'circle'],
            'colors' => [['#5A0F1C', '#FFF8E7'], ['#3B0F3F', '#FFFFFF'], ['#12351F', '#FFF8E7']],
            'finder_accent' => ['#0B2A6B', null],
            'frame' => [self::CORAL, self::LIME],
            'border' => ['zigzag', 'dashed'],
            'border_color' => [self::NAVY],
            'logo' => ['bite', 'none'],
            'label' => ['Grab a bite, scan in'],
        ],
        'editorial' => [
            'module' => ['square'],
            'finder' => ['square', 'rounded'],
            'colors' => [[self::INK, '#FFF8E7'], ['#12351F', '#FBFAF5']],
            'finder_accent' => [null],
            'frame' => ['#FFF8E7', '#FBFAF5'],
            'border' => ['double'],
            'border_color' => [self::INK],
            'logo' => ['none'],
            'label' => ['Scan to view the event'],
        ],
    ];

    /**
     * A new design for the preset ("random" picks a preset first). For custom_theme, $flyerPalette is the
     * list of colours sampled from the event's flyer; without a usable palette a random preset is used.
     *
     * @param list<string>|null $flyerPalette
     * @return array<string, mixed>
     */
    public static function generate(string $preset = 'random', ?array $flyerPalette = null): array
    {
        if ($preset === 'random' || !isset(self::PRESETS[$preset])) {
            $choices = ['geometric', 'circular', 'neon', 'minimal', 'festival', 'editorial', 'random_mix'];
            $preset = $choices[random_int(0, count($choices) - 1)];
        }

        $design = match ($preset) {
            'random_mix' => self::randomMix(),
            'custom_theme' => self::customTheme($flyerPalette ?? []),
            default => self::fromOptions($preset, self::OPTIONS[$preset]),
        };

        return self::safe($design);
    }

    /**
     * Returns the design unchanged when it passes every rule, or the conventional fallback design
     * (marked with fallback_reason) when it does not.
     *
     * @param array<string, mixed> $design
     * @return array<string, mixed>
     */
    public static function safe(array $design): array
    {
        $problem = self::problemWith($design);
        if ($problem === null) {
            return $design;
        }

        return self::fallback($problem, (string) ($design['preset'] ?? 'unknown'));
    }

    /** @param array<string, mixed> $design */
    public static function problemWith(array $design): ?string
    {
        foreach (['module' => self::MODULE_SHAPES, 'finder' => self::FINDER_SHAPES, 'border' => self::BORDERS, 'logo' => self::LOGOS, 'ecc' => self::ECC_LEVELS] as $key => $allowed) {
            if (!in_array($design[$key] ?? null, $allowed, true)) {
                return "unsupported {$key}";
            }
        }

        foreach (['fg', 'plate', 'frame', 'border_color'] as $key) {
            if (!self::isHex($design[$key] ?? null)) {
                return "invalid colour for {$key}";
            }
        }
        $accent = $design['finder_accent'] ?? null;
        if ($accent !== null && !self::isHex($accent)) {
            return 'invalid colour for finder_accent';
        }

        foreach (array_filter([$design['fg'], $accent]) as $dark) {
            if (self::luminance($dark) >= self::luminance($design['plate'])) {
                return 'modules must be darker than the plate';
            }
            if (self::contrast($dark, $design['plate']) < self::MIN_CONTRAST) {
                return 'contrast below 7:1';
            }
        }

        if ($design['module'] === 'dot' && $design['ecc'] === 'M') {
            return 'dot modules need error correction Q or H';
        }
        if ($design['logo'] !== 'none' && $design['ecc'] !== 'H') {
            return 'a centre logo needs error correction H';
        }

        return null;
    }

    /**
     * The conventional design used whenever another design fails a rule or the decode check:
     * black square modules on white, standard corner markers, medium error correction.
     *
     * @return array<string, mixed>
     */
    public static function fallback(?string $reason = null, ?string $requestedPreset = null): array
    {
        return array_filter([
            'version' => self::VERSION,
            'preset' => 'minimal',
            'module' => 'square',
            'finder' => 'square',
            'fg' => '#000000',
            'plate' => '#FFFFFF',
            'finder_accent' => null,
            'frame' => '#FFFFFF',
            'border' => 'none',
            'border_color' => '#000000',
            'logo' => 'none',
            'label' => null,
            'ecc' => 'M',
            'fallback_reason' => $reason,
            'requested_preset' => $requestedPreset,
        ], fn ($value, $key) => $value !== null || in_array($key, ['finder_accent', 'label'], true), ARRAY_FILTER_USE_BOTH);
    }

    public static function label(string $preset): string
    {
        return self::PRESETS[$preset] ?? ucfirst(str_replace('_', ' ', $preset));
    }

    /**
     * @param array<string, list<mixed>> $options
     * @return array<string, mixed>
     */
    private static function fromOptions(string $preset, array $options): array
    {
        [$fg, $plate] = self::pick($options['colors']);
        $module = self::pick($options['module']);
        $logo = self::pick($options['logo']);

        return [
            'version' => self::VERSION,
            'preset' => $preset,
            'module' => $module,
            'finder' => self::pick($options['finder']),
            'fg' => $fg,
            'plate' => $plate,
            'finder_accent' => self::pick($options['finder_accent']),
            'frame' => self::pick($options['frame']),
            'border' => self::pick($options['border']),
            'border_color' => self::pick($options['border_color']),
            'logo' => $logo,
            'label' => self::pick($options['label']),
            'ecc' => self::eccFor($module, $logo),
        ];
    }

    /**
     * Style H: one compatible element from each list. Colours come from the tested pairs of all presets,
     * so the combination is new but each ingredient is approved.
     *
     * @return array<string, mixed>
     */
    private static function randomMix(): array
    {
        $pairs = [];
        $frames = [];
        foreach (self::OPTIONS as $options) {
            array_push($pairs, ...$options['colors']);
            array_push($frames, ...$options['frame']);
        }
        [$fg, $plate] = self::pick($pairs);
        $module = self::pick(self::MODULE_SHAPES);
        $logo = self::pick(self::LOGOS);

        return [
            'version' => self::VERSION,
            'preset' => 'random_mix',
            'module' => $module,
            'finder' => self::pick(self::FINDER_SHAPES),
            'fg' => $fg,
            'plate' => $plate,
            'finder_accent' => self::pick([null, '#5A0F1C', '#0B2A6B', '#3B0F3F']),
            'frame' => self::pick(array_values(array_unique($frames))),
            'border' => self::pick(self::BORDERS),
            'border_color' => self::pick([self::NAVY, self::LIME, self::CORAL, self::INK]),
            'logo' => $logo,
            'label' => self::pick([null, 'Scan for the event']),
            'ecc' => self::eccFor($module, $logo),
        ];
    }

    /**
     * Style G: colours taken from the event's flyer. The darkest sampled colour that reaches 7:1 on a
     * white plate becomes the module colour; the most saturated sampled colour becomes the frame.
     * The flyer's artwork itself is never placed near the code.
     *
     * @param list<string> $palette
     * @return array<string, mixed>
     */
    private static function customTheme(array $palette): array
    {
        $palette = array_values(array_filter($palette, fn ($c) => self::isHex($c)));
        $plate = '#FFFFFF';
        $darks = array_values(array_filter($palette, fn ($c) => self::contrast($c, $plate) >= self::MIN_CONTRAST));
        usort($darks, fn ($a, $b) => self::luminance($a) <=> self::luminance($b));

        if ($darks === []) {
            return self::fallback('the flyer has no colour dark enough for a readable code', 'custom_theme');
        }

        $byChroma = $palette;
        usort($byChroma, fn ($a, $b) => self::chroma($b) <=> self::chroma($a));

        return [
            'version' => self::VERSION,
            'preset' => 'custom_theme',
            'module' => 'rounded',
            'finder' => 'rounded',
            'fg' => $darks[0],
            'plate' => $plate,
            'finder_accent' => null,
            'frame' => $byChroma[0] ?? $darks[0],
            'border' => 'line',
            'border_color' => $darks[0],
            'logo' => 'none',
            'label' => 'Scan for the event',
            'ecc' => 'Q',
            'palette_source' => 'flyer',
        ];
    }

    private static function eccFor(string $module, string $logo): string
    {
        if ($logo !== 'none') {
            return 'H';
        }

        return $module === 'dot' ? 'Q' : 'M';
    }

    /**
     * @template T
     * @param list<T> $items
     * @return T
     */
    private static function pick(array $items): mixed
    {
        return $items[random_int(0, count($items) - 1)];
    }

    public static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
    }

    /** @return array{int, int, int} */
    public static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    /** WCAG relative luminance. */
    public static function luminance(string $hex): float
    {
        $channels = array_map(function (int $c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private static function chroma(string $hex): int
    {
        $rgb = self::rgb($hex);

        return max($rgb) - min($rgb);
    }
}
