<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The EventBite brand: derivatives of the official logo exist at their real proportions, the app icons and
 * manifest use the brand background, and the logo component never stretches the logo.
 */
class BrandIdentityTest extends TestCase
{
    private const LOCKUP_RATIO = 1594 / 543;
    private const MARK_RATIO = 450 / 543;

    public function test_logo_derivatives_exist_with_the_official_proportions_and_transparency(): void
    {
        foreach ([1200, 600, 360] as $width) {
            foreach (['png', 'webp'] as $ext) {
                $path = public_path("images/brand/eventbite-logo-{$width}.{$ext}");
                $this->assertFileExists($path);
                [$w, $h] = getimagesize($path);
                $this->assertSame($width, $w);
                $this->assertEqualsWithDelta(self::LOCKUP_RATIO, $w / $h, 0.02, "logo-{$width}.{$ext} keeps the lockup ratio");
            }
        }
        foreach ([512, 256, 128] as $width) {
            $path = public_path("images/brand/eventbite-mark-{$width}.png");
            $this->assertFileExists($path);
            [$w, $h] = getimagesize($path);
            $this->assertSame($width, $w);
            $this->assertEqualsWithDelta(self::MARK_RATIO, $w / $h, 0.02, "mark-{$width} keeps the mark ratio");
        }

        // The derivatives sit on any dark surface without a box: their corners are fully transparent.
        if (function_exists('imagecreatefrompng')) {
            $image = imagecreatefrompng(public_path('images/brand/eventbite-logo-600.png'));
            $alpha = (imagecolorat($image, 0, 0) >> 24) & 0x7F;
            $this->assertSame(127, $alpha, 'The flat background is removed.');
        }
    }

    public function test_app_icons_are_square_and_the_manifest_uses_the_brand_background(): void
    {
        foreach ([16, 32, 48, 64, 96, 128, 180, 192, 512] as $size) {
            $path = public_path("icons/eventbite-app-{$size}.png");
            $this->assertFileExists($path);
            [$w, $h] = getimagesize($path);
            $this->assertSame([$size, $size], [$w, $h], "app icon {$size}");
        }
        $this->assertFileExists(public_path('icons/eventbite-maskable-512.png'));

        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true);
        $this->assertSame('#07111F', strtoupper($manifest['theme_color']));
        $this->assertSame('#07111F', strtoupper($manifest['background_color']));
        $purposes = array_column($manifest['icons'], 'purpose');
        $this->assertContains('maskable', $purposes);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    public function test_logo_component_variants_keep_the_logo_proportions(): void
    {
        $expect = [
            'wordmark' => ['eventbite-logo-360.webp', self::LOCKUP_RATIO],
            'compact' => ['eventbite-logo-360.webp', self::LOCKUP_RATIO],
            'icon' => ['eventbite-mark-128.png', self::MARK_RATIO],
            'email' => ['eventbite-logo-600.png', self::LOCKUP_RATIO],
        ];
        foreach ($expect as $variant => [$file, $ratio]) {
            $html = Blade::render('<x-brand-logo :variant="$v" />', ['v' => $variant]);
            $this->assertStringContainsString($file, $html, $variant);
            $this->assertStringContainsString('alt="EventBite"', $html);
            preg_match('/width="(\d+)"/', $html, $w);
            preg_match('/height="(\d+)"/', $html, $h);
            $this->assertEqualsWithDelta($ratio, $w[1] / $h[1], 0.05, "{$variant} is not stretched");
        }

        // Mail clients do not all show WebP, so the email logo is PNG only.
        $this->assertStringNotContainsString('.webp', Blade::render('<x-brand-logo variant="email" />'));
    }

    public function test_layouts_point_at_the_new_app_icons_and_theme_colour(): void
    {
        foreach (['layouts/app.blade.php', 'layouts/admin.blade.php'] as $layout) {
            $source = file_get_contents(resource_path("views/{$layout}"));
            $this->assertStringContainsString('icons/eventbite-app-180.png', $source, $layout);
            $this->assertMatchesRegularExpression('/theme-color" content="#07111F"/i', $source, $layout);
        }
    }
}
