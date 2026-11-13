<?php

declare(strict_types=1);

namespace App\Services\EventQr;

use App\Models\Event;
use App\Models\SystemPlatformSetting;
use App\Support\TicketTheme;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * The look of attendee ticket QR codes: approved, event-themed presets that hosts choose from, within global
 * controls set by administrators. Designs use the same schema, renderer and rules as the promotional event QR
 * (QrDesignCatalog, QrArtworkRenderer), so there is one QR design system, not two.
 *
 * How a ticket's design is decided (design()):
 *   1. Administrators can turn themed ticket codes off: every ticket then uses the conventional design.
 *   2. The host's choice for the event (events.ticket_qr_design), if its preset is approved; otherwise the
 *      administrators' default; "auto" follows the kind of event the host declared (App\Support\TicketTheme).
 *   3. Shapes, centre mark and colours may only take values the preset lists; anything else falls back to the
 *      preset's own first choice. Custom colours are used only with the Cultural Celebration preset and only when
 *      administrators allow them.
 *   4. The result must pass every readability rule (readability()) or the conventional design is used instead.
 *
 * Readability rules, never relaxed by any setting:
 *   - normal polarity (modules darker than the plate) and contrast of at least 7:1 for modules and corner
 *     markers (administrators may require more, never less);
 *   - the 4-module quiet zone in the plate colour, with frames, borders and labels outside it;
 *   - the three finder patterns drawn whole in an approved shape, never covered;
 *   - error correction Q at least, H whenever a centre mark is drawn, and the centre mark only inside the area
 *     the encoder clears for it (at most 6% of the symbol, away from the finder patterns);
 *   - at least 3 screen pixels per module at the ticket's display size;
 *   - the artwork must decode back to the exact credential with a real QR reader (large, small and JPEG-compressed).
 *
 * Event photos or flyers are never drawn on the code: centre branding is the EventBite mark or the event's
 * initials, drawn as flat shapes in the cleared centre.
 */
final class TicketQrDesignSystem
{
    public const VERSION = 1;

    /** Ticket QR codes are shown at this size (CSS pixels) on the ticket page. */
    public const DISPLAY_PIXELS = 260;
    public const MIN_PIXELS_PER_MODULE = 3.0;
    public const MIN_CONTRAST = 7.0;
    private const MAX_LOGO_FRACTION = 0.06;

    /** A credential-shaped sample, for previews and certification: same length, so the same QR version. */
    public const SAMPLE_CREDENTIAL = 'tkt_SAMPLE-preview-0000000000000000000000000000';

    private const NAVY = '#07111F';
    private const LIME = '#C7FF45';

    /**
     * Approved presets. Colours: primary = modules, secondary = corner markers and accents, background = the plate
     * behind the code; frame and border sit outside the quiet zone. Each list of options is a whitelist; its first
     * entry is the default.
     *
     * @var array<string, array<string, mixed>>
     */
    public const PRESETS = [
        'eventbite_classic' => [
            'label' => 'EventBite Classic',
            'description' => 'Navy on white with lime corner brackets. Works for any event.',
            'primary' => self::NAVY, 'secondary' => '#1E2B4A', 'background' => '#FFFFFF',
            'frame' => self::NAVY, 'border' => ['corners', 'line'], 'border_color' => self::LIME,
            'module' => ['rounded', 'square', 'dot'], 'finder' => ['rounded', 'square', 'circle'],
            'logo' => ['none', 'bite', 'monogram'], 'qr_label' => 'Admit one', 'theme' => 'classic',
        ],
        'caribbean_festival' => [
            'label' => 'Caribbean Festival',
            'description' => 'Green, gold and black, for Jamaican and Caribbean cultural festivals.',
            'primary' => '#0B0F0C', 'secondary' => '#00552A', 'background' => '#FFFBEA',
            'frame' => '#FED100', 'border' => ['zigzag', 'line'], 'border_color' => '#0B0F0C',
            'module' => ['rounded', 'dot', 'square'], 'finder' => ['leaf', 'rounded', 'circle'],
            'logo' => ['none', 'monogram', 'bite'], 'qr_label' => 'Admit one', 'theme' => 'island',
        ],
        'music_festival' => [
            'label' => 'Music Festival',
            'description' => 'Deep purple and electric lime on a dark frame.',
            'primary' => '#14061F', 'secondary' => '#4B1585', 'background' => '#FFFFFF',
            'frame' => '#120A24', 'border' => ['double', 'line', 'corners'], 'border_color' => self::LIME,
            'module' => ['dot', 'rounded'], 'finder' => ['circle', 'rounded'],
            'logo' => ['none', 'bite', 'monogram'], 'qr_label' => 'Admit one', 'theme' => 'stage',
        ],
        'food_festival' => [
            'label' => 'Food Festival',
            'description' => 'Warm orange, chilli red and cream.',
            'primary' => '#7A1408', 'secondary' => '#8C2A00', 'background' => '#FFF6E5',
            'frame' => '#FF7A1A', 'border' => ['dashed', 'zigzag', 'line'], 'border_color' => '#7A1408',
            'module' => ['rounded', 'dot'], 'finder' => ['rounded', 'leaf'],
            'logo' => ['none', 'bite', 'monogram'], 'qr_label' => 'Admit one', 'theme' => 'feast',
        ],
        'corporate' => [
            'label' => 'Corporate',
            'description' => 'Navy, silver and white. Quiet and precise.',
            'primary' => '#0B1F3A', 'secondary' => '#1C3A63', 'background' => '#FFFFFF',
            'frame' => '#C9CED6', 'border' => ['line', 'none'], 'border_color' => '#0B1F3A',
            'module' => ['square', 'rounded'], 'finder' => ['square', 'rounded'],
            'logo' => ['none', 'monogram'], 'qr_label' => 'Admit one', 'theme' => 'professional',
        ],
        'wedding' => [
            'label' => 'Wedding',
            'description' => 'Warm charcoal on ivory with champagne details.',
            'primary' => '#2E2A26', 'secondary' => '#5B4636', 'background' => '#FFFDF7',
            'frame' => '#EFE6D6', 'border' => ['double', 'line'], 'border_color' => '#A88B5C',
            'module' => ['dot', 'rounded'], 'finder' => ['circle', 'leaf', 'rounded'],
            'logo' => ['none', 'monogram'], 'qr_label' => 'Admit one', 'theme' => 'celebration',
        ],
        'cultural_celebration' => [
            'label' => 'Cultural Celebration',
            'description' => 'Your event\'s own colours. Colours that would make the code hard to scan are refused.',
            'primary' => '#12351F', 'secondary' => '#3B0F3F', 'background' => '#FFFFFF',
            'frame' => '#E7C873', 'border' => ['line', 'double', 'zigzag', 'dashed', 'corners', 'none'], 'border_color' => '#12351F',
            'module' => ['rounded', 'dot', 'square'], 'finder' => ['rounded', 'leaf', 'circle', 'square'],
            'logo' => ['none', 'monogram', 'bite'], 'qr_label' => 'Admit one', 'theme' => 'community',
            'custom_palette' => true,
        ],
    ];

    public const LOGO_LABELS = ['none' => 'None', 'bite' => 'EventBite mark', 'monogram' => 'Event initials'];
    public const MODULE_LABELS = ['square' => 'Squares', 'rounded' => 'Rounded squares', 'dot' => 'Circles'];
    public const FINDER_LABELS = ['square' => 'Square', 'rounded' => 'Rounded', 'circle' => 'Circle', 'leaf' => 'Leaf'];

    /** Defaults for the administrators' global controls (system_platform_settings.ticket_qr_controls). */
    public const DEFAULT_CONTROLS = [
        'enabled' => true,
        'default_preset' => 'auto',
        'allowed_presets' => null, // null = every preset
        'allow_custom_palette' => true,
        'allow_center_logo' => true,
        'min_contrast' => self::MIN_CONTRAST,
    ];

    public function __construct(private readonly QrArtworkRenderer $renderer)
    {
    }

    /** @return array{enabled: bool, default_preset: string, allowed_presets: list<string>, allow_custom_palette: bool, allow_center_logo: bool, min_contrast: float} */
    public function controls(): array
    {
        $stored = [];
        try {
            $raw = SystemPlatformSetting::query()->value('ticket_qr_controls');
            $stored = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);
        } catch (\Throwable) {
            // No settings table yet (fresh install): the defaults apply.
        }

        return self::normaliseControls($stored);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{enabled: bool, default_preset: string, allowed_presets: list<string>, allow_custom_palette: bool, allow_center_logo: bool, min_contrast: float}
     */
    public static function normaliseControls(array $input): array
    {
        $c = $input + self::DEFAULT_CONTROLS;
        $allowed = is_array($c['allowed_presets'])
            ? array_values(array_intersect(array_keys(self::PRESETS), $c['allowed_presets']))
            : array_keys(self::PRESETS);
        if ($allowed === []) {
            $allowed = ['eventbite_classic'];
        }
        $default = (string) $c['default_preset'];
        if ($default !== 'auto' && !in_array($default, $allowed, true)) {
            $default = 'auto';
        }

        return [
            'enabled' => (bool) $c['enabled'],
            'default_preset' => $default,
            'allowed_presets' => $allowed,
            'allow_custom_palette' => (bool) $c['allow_custom_palette'],
            'allow_center_logo' => (bool) $c['allow_center_logo'],
            // Administrators may ask for more contrast than the floor, never less.
            'min_contrast' => max(self::MIN_CONTRAST, min(21.0, (float) $c['min_contrast'])),
        ];
    }

    /**
     * The conventional design for tickets: black squares on white, standard corner markers, error correction Q.
     *
     * @return array<string, mixed>
     */
    public static function fallback(?string $reason = null, ?string $requestedPreset = null): array
    {
        return ['ecc' => 'Q', 'system' => 'ticket', 'system_version' => self::VERSION]
            + QrDesignCatalog::fallback($reason, $requestedPreset);
    }

    /** The preset the event's theme suggests, used for "auto". */
    public static function presetForTheme(?Event $event): string
    {
        $theme = TicketTheme::for($event)->key;
        foreach (self::PRESETS as $key => $preset) {
            if ($preset['theme'] === $theme) {
                return $key;
            }
        }

        return 'eventbite_classic';
    }

    /** The event's ticket QR design (always one that passes every rule). @return array<string, mixed> */
    public function design(Event $event): array
    {
        return $this->resolve($event, is_array($event->ticket_qr_design) ? $event->ticket_qr_design : []);
    }

    /**
     * The design a host choice would produce, before it is saved (previews and validation).
     *
     * @param array<string, mixed> $choice
     * @return array<string, mixed>
     */
    public function resolve(?Event $event, array $choice): array
    {
        $controls = $this->controls();
        if (!$controls['enabled']) {
            return self::fallback('themed ticket QR codes are turned off by the administrators');
        }

        $presetKey = $this->presetKey($event, (string) ($choice['preset'] ?? ''), $controls);
        $preset = self::PRESETS[$presetKey];

        $pick = fn (string $key, array $allowed) => in_array($choice[$key] ?? null, $allowed, true) ? $choice[$key] : $allowed[0];
        $logoChoices = $controls['allow_center_logo'] ? $preset['logo'] : ['none'];
        $logo = $pick('logo', $logoChoices);
        $monogram = $logo === 'monogram' ? self::monogramFor($event, $choice['monogram'] ?? null) : null;
        if ($logo === 'monogram' && $monogram === null) {
            $logo = 'none';
        }

        $primary = $preset['primary'];
        $secondary = $preset['secondary'];
        $background = $preset['background'];
        $frame = $preset['frame'];
        $borderColor = $preset['border_color'];
        if (!empty($preset['custom_palette']) && $controls['allow_custom_palette']) {
            $primary = self::hexOr($choice['primary'] ?? null, $primary);
            $secondary = self::hexOr($choice['secondary'] ?? null, $secondary);
            $background = self::hexOr($choice['background'] ?? null, $background);
            $frame = self::hexOr($choice['frame'] ?? null, $frame);
            $borderColor = $primary;
        }

        $module = $pick('module', $preset['module']);
        $design = [
            'version' => QrDesignCatalog::VERSION,
            'system' => 'ticket',
            'system_version' => self::VERSION,
            'preset' => $presetKey,
            'module' => $module,
            'finder' => $pick('finder', $preset['finder']),
            'fg' => strtoupper($primary),
            'plate' => strtoupper($background),
            'finder_accent' => strtoupper($secondary) === strtoupper($primary) ? null : strtoupper($secondary),
            'frame' => strtoupper($frame),
            'border' => $pick('border', $preset['border']),
            'border_color' => strtoupper($borderColor),
            'logo' => $logo,
            'label' => $preset['qr_label'],
            // Tickets are scanned in dark venues off cracked screens: Q at least, H with a centre mark.
            'ecc' => $logo !== 'none' ? 'H' : 'Q',
        ];
        if ($monogram !== null) {
            $design['monogram'] = $monogram;
        }

        $problem = $this->ruleProblem($design, $controls['min_contrast']);
        if ($problem !== null) {
            return self::fallback($problem, $presetKey);
        }

        return $design;
    }

    /**
     * Why a host's choice cannot be used as given, or null when it is fine. Used to refuse a save with a clear
     * message instead of silently falling back.
     *
     * @param array<string, mixed> $choice
     */
    public function choiceProblem(?Event $event, array $choice): ?string
    {
        $controls = $this->controls();
        $preset = (string) ($choice['preset'] ?? '');
        if ($preset !== 'auto' && !in_array($preset, $controls['allowed_presets'], true)) {
            return 'That QR design is not available. Choose one of the approved designs.';
        }
        $spec = self::PRESETS[$preset === 'auto' ? self::presetForTheme($event) : $preset] ?? self::PRESETS['eventbite_classic'];
        foreach (['module' => 'shape', 'finder' => 'corner style', 'border' => 'border'] as $key => $name) {
            if (isset($choice[$key]) && !in_array($choice[$key], $spec[$key], true)) {
                return "That {$name} is not part of the {$spec['label']} design.";
            }
        }
        if (isset($choice['logo']) && $choice['logo'] !== 'none') {
            if (!$controls['allow_center_logo']) {
                return 'Centre branding on ticket QR codes is turned off by the administrators.';
            }
            if (!in_array($choice['logo'], $spec['logo'], true)) {
                return "That centre mark is not part of the {$spec['label']} design.";
            }
        }
        $customColours = array_filter([$choice['primary'] ?? null, $choice['secondary'] ?? null, $choice['background'] ?? null, $choice['frame'] ?? null]);
        if ($customColours !== []) {
            if (empty($spec['custom_palette'])) {
                return 'Custom colours are only available with the Cultural Celebration design.';
            }
            if (!$controls['allow_custom_palette']) {
                return 'Custom QR colours are turned off by the administrators.';
            }
            foreach ($customColours as $colour) {
                if (!QrDesignCatalog::isHex($colour)) {
                    return 'Colours must be hex values like #12351F.';
                }
            }
        }

        $design = $this->resolve($event, ['preset' => $preset] + $choice);
        if (isset($design['fallback_reason'])) {
            return 'Those colours would make the code hard to scan (' . $design['fallback_reason'] . '). Use a darker main colour on a lighter background.';
        }

        return null;
    }

    /**
     * Every readability check for a design, with the evidence. Decoding is optional (it is the slow part).
     *
     * @param array<string, mixed> $design
     * @return array{passes: bool, checks: array<string, array{pass: bool, detail: string}>}
     */
    public function readability(array $design, string $data = self::SAMPLE_CREDENTIAL, bool $decode = true): array
    {
        $min = $this->controls()['min_contrast'];
        $checks = [];

        $fg = (string) ($design['fg'] ?? '#000000');
        $plate = (string) ($design['plate'] ?? '#FFFFFF');
        $accent = $design['finder_accent'] ?? null;
        $valid = QrDesignCatalog::isHex($fg) && QrDesignCatalog::isHex($plate) && ($accent === null || QrDesignCatalog::isHex($accent));

        $checks['polarity'] = [
            'pass' => $valid && QrDesignCatalog::luminance($fg) < QrDesignCatalog::luminance($plate),
            'detail' => 'Dark modules on a lighter plate (normal polarity).',
        ];
        $contrast = $valid ? QrDesignCatalog::contrast($fg, $plate) : 0.0;
        $accentContrast = $valid && $accent !== null ? QrDesignCatalog::contrast($accent, $plate) : $contrast;
        $checks['contrast'] = [
            'pass' => min($contrast, $accentContrast) >= $min,
            'detail' => sprintf('Modules %.1f:1, corner markers %.1f:1 (minimum %.1f:1).', $contrast, $accentContrast, $min),
        ];
        $checks['quiet_zone'] = [
            'pass' => QrArtworkRenderer::QUIET_ZONE >= 4,
            'detail' => QrArtworkRenderer::QUIET_ZONE . '-module quiet zone in the plate colour; frame and label outside it.',
        ];
        $logo = (string) ($design['logo'] ?? 'none');
        $ecc = (string) ($design['ecc'] ?? 'M');
        $checks['error_correction'] = [
            'pass' => in_array($ecc, ['Q', 'H'], true) && ($logo === 'none' || $ecc === 'H'),
            'detail' => "Level {$ecc}" . ($logo !== 'none' ? ' with a centre mark (H required).' : ' (Q or H required).'),
        ];
        $checks['finder_patterns'] = [
            'pass' => in_array($design['finder'] ?? null, QrDesignCatalog::FINDER_SHAPES, true),
            'detail' => 'All three finder patterns drawn whole in an approved shape.',
        ];

        $rules = QrDesignCatalog::problemWith($design);
        $checks['design_rules'] = ['pass' => $rules === null, 'detail' => $rules ?? 'Approved shapes, colours and options only.'];

        try {
            $matrix = $this->renderer->matrix($data, $design);
            $size = $matrix['size'];
            $box = $matrix['logo'];
            $logoOk = true;
            $logoDetail = 'No centre mark.';
            if ($box !== null) {
                $fraction = ($box['w'] * $box['h']) / ($size * $size);
                $clearOfFinders = $box['x'] > 8 && $box['y'] > 8 && $box['x'] + $box['w'] < $size - 8 && $box['y'] + $box['h'] < $size - 8;
                $logoOk = $fraction <= self::MAX_LOGO_FRACTION && $clearOfFinders;
                $logoDetail = sprintf('Centre mark covers %.1f%% of the symbol, inside the area cleared by the encoder.', $fraction * 100);
            }
            $checks['centre_mark'] = ['pass' => $logoOk, 'detail' => $logoDetail];

            $modulesAcross = $size + 2 * QrArtworkRenderer::QUIET_ZONE + 6; // + the frame
            $ppm = self::DISPLAY_PIXELS / $modulesAcross;
            $checks['resolution'] = [
                'pass' => $ppm >= self::MIN_PIXELS_PER_MODULE,
                'detail' => sprintf('%d×%d modules; %.1f px per module at %d px.', $size, $size, $ppm, self::DISPLAY_PIXELS),
            ];
        } catch (\Throwable $e) {
            $checks['centre_mark'] = ['pass' => false, 'detail' => 'The matrix could not be built: ' . $e->getMessage()];
            $checks['resolution'] = ['pass' => false, 'detail' => 'Not measured.'];
        }

        if ($decode) {
            $checks['decode'] = [
                'pass' => $this->decodesReliably($data, $design),
                'detail' => 'Read back exactly by a QR decoder at print size, at small size and after JPEG compression.',
            ];
        }

        return [
            'passes' => !in_array(false, array_column($checks, 'pass'), true),
            'checks' => $checks,
        ];
    }

    /** @param array<string, mixed> $design */
    public function decodesReliably(string $data, array $design): bool
    {
        try {
            $large = $this->renderer->png($data, $design, 8);
            $small = $this->renderer->png($data, $design, 4);

            return $this->decodes($large, $data) && $this->decodes($small, $data) && $this->decodes(self::jpeg($large, 60), $data);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $design */
    public function decodesOnce(string $data, array $design, int $pixelsPerModule = 5): bool
    {
        try {
            return $this->decodes($this->renderer->png($data, $design, $pixelsPerModule), $data);
        } catch (\Throwable) {
            return false;
        }
    }

    /** A stable fingerprint of a design: tickets re-render only when their event's design changes. @param array<string, mixed> $design */
    public static function fingerprint(array $design): string
    {
        $keep = array_diff_key($design, ['fallback_reason' => true, 'requested_preset' => true]);
        ksort($keep);

        return hash('sha256', (string) json_encode($keep));
    }

    /** @return array<string, string> key => label, only the presets hosts may choose now */
    public function availablePresets(): array
    {
        $out = [];
        foreach ($this->controls()['allowed_presets'] as $key) {
            $out[$key] = self::PRESETS[$key]['label'];
        }

        return $out;
    }

    private function decodes(string $image, string $expected): bool
    {
        try {
            return (string) (new QRCode(new QROptions(['readerGrayscale' => true])))->readFromBlob($image) === $expected;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $design */
    private function ruleProblem(array $design, float $minContrast): ?string
    {
        $problem = QrDesignCatalog::problemWith($design);
        if ($problem !== null) {
            return $problem;
        }
        foreach (array_filter([$design['fg'], $design['finder_accent'] ?? null]) as $dark) {
            if (QrDesignCatalog::contrast($dark, $design['plate']) < $minContrast) {
                return sprintf('contrast below the required %.1f:1', $minContrast);
            }
        }

        return null;
    }

    /** @param array{allowed_presets: list<string>, default_preset: string} $controls */
    private function presetKey(?Event $event, string $requested, array $controls): string
    {
        $candidates = [$requested, $controls['default_preset'], 'auto'];
        foreach ($candidates as $candidate) {
            $key = $candidate === 'auto' ? self::presetForTheme($event) : $candidate;
            if (isset(self::PRESETS[$key]) && in_array($key, $controls['allowed_presets'], true)) {
                return $key;
            }
        }

        return $controls['allowed_presets'][0];
    }

    /** One to three capitals from the host's choice, or the initials of the event's title. */
    public static function monogramFor(?Event $event, mixed $requested): ?string
    {
        $clean = fn (string $text) => preg_replace('/[^A-Z0-9&]/', '', strtoupper($text)) ?? '';
        $letters = is_string($requested) ? $clean($requested) : '';
        if ($letters === '' && $event !== null) {
            $words = preg_split('/\s+/', trim((string) $event->title)) ?: [];
            $words = array_values(array_filter($words, fn ($w) => !in_array(mb_strtolower($w), ['the', 'a', 'an', 'of', 'and', '&'], true)));
            $letters = $clean(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), $words)));
        }
        $letters = substr($letters, 0, 3);

        return $letters === '' ? null : $letters;
    }

    private static function hexOr(mixed $value, string $default): string
    {
        return QrDesignCatalog::isHex($value) ? strtoupper((string) $value) : $default;
    }

    private static function jpeg(string $png, int $quality): string
    {
        $img = imagecreatefromstring($png);
        ob_start();
        imagejpeg($img, null, $quality);
        $jpeg = (string) ob_get_clean();
        imagedestroy($img);

        return $jpeg;
    }
}
