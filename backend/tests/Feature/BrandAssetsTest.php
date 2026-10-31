<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BrandAssetsTest extends TestCase
{
    public function test_blade_brand_component_uses_the_public_eventbite_wordmark(): void
    {
        URL::forceRootUrl('https://events.example.com');
        URL::forceScheme('https');

        $markup = Blade::render('<x-brand-logo variant="compact" />');

        $this->assertStringContainsString('https://events.example.com/images/brand/eventbite-wordmark.webp', $markup);
        $this->assertStringContainsString('alt="EventBite"', $markup);
        $this->assertStringContainsString('width="144"', $markup);
        $this->assertStringContainsString('height="51"', $markup);
    }

    public function test_transactional_email_includes_the_public_logo_and_text_fallback(): void
    {
        URL::forceRootUrl('https://events.example.com');
        URL::forceScheme('https');
        $log = new NotificationLog(['message' => 'Your order EB-100 is confirmed.']);

        $html = view('emails.notification', ['log' => $log])->render();
        $text = view('emails.notification-text', ['log' => $log])->render();

        $this->assertStringContainsString('https://events.example.com/images/brand/eventbite-wordmark.webp', $html);
        $this->assertStringContainsString('alt="EventBite"', $html);
        $this->assertStringContainsString('Your order EB-100 is confirmed.', $html);
        $this->assertStringContainsString('EventBite', $text);
        $this->assertStringContainsString('Your order EB-100 is confirmed.', $text);
    }

    public function test_device_icon_is_a_prepared_square_asset(): void
    {
        $dimensions = getimagesize(public_path('icons/eventbite-192.png'));

        $this->assertSame([192, 192], [$dimensions[0], $dimensions[1]]);
    }
}
