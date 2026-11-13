<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderPlacementService;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Event-day use of the ticket page: the offline copy (public/ticket-offline-sw.js) is clearly a saved display and never
 * an admission confirmation, it is limited to ticket pages, signing out wipes it, and the page's wallet section and
 * images follow the accessibility and loading rules.
 */
class TicketOfflineAndAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        config(['wallet.apple.enabled' => false, 'wallet.google.enabled' => false, 'wallet.samsung.enabled' => false]);
        Event::allTenants()->findOrFail(self::EVENT)->forceFill([
            'starts_at' => now()->addDays(5)->setTime(18, 0), 'ends_at' => now()->addDays(5)->setTime(23, 0),
            'event_date_iso' => now()->addDays(5)->toDateString(), 'status' => 'published',
        ])->save();
    }

    private function attendee(): User
    {
        return User::where('email', 'attendee@islandevents.example')->firstOrFail();
    }

    private function order(bool $paid = true): Order
    {
        $item = MenuItem::withoutGlobalScopes()->where('event_id', self::EVENT)->where('is_active', true)->firstOrFail();
        $order = app(OrderPlacementService::class)->placeOrder(Event::allTenants()->findOrFail(self::EVENT), [
            'customer_name' => 'Offline Holder', 'customer_phone' => '+1 305 555 0142', 'method' => 'bank',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ], $this->attendee())['order']->fresh();
        if ($paid) {
            $order->forceFill(['status' => 'paid', 'amount_paid_minor' => $order->total_minor])->save();
        }

        return $order->fresh();
    }

    public function test_the_offline_notice_is_hidden_online_and_says_the_gate_decides_admission(): void
    {
        $html = $this->actingAs($this->attendee())->get(route('tickets.show', $this->order()))->assertOk()->getContent();

        // The exact marker the service worker switches on when it serves the saved copy.
        $this->assertStringContainsString('data-offline-copy hidden', $html);
        $this->assertStringContainsString('Saved copy · you’re offline', $html);
        $this->assertStringContainsString('This page doesn’t confirm admission:</strong> only the staff scan at the gate does', $html);
        $this->assertStringContainsString('js/ticket-collection.js', $html);
    }

    public function test_the_service_worker_only_keeps_ticket_pages_and_prefers_the_network(): void
    {
        $sw = file_get_contents(public_path('ticket-offline-sw.js'));
        $js = file_get_contents(public_path('js/ticket-collection.js'));

        $this->assertStringContainsString("register('/ticket-offline-sw.js', { scope: '/tickets/' })", $js);
        $this->assertStringContainsString("const TICKET_PAGE = /^\\/tickets\\/[0-9a-f-]{36}$/i;", $sw);
        $this->assertStringContainsString("if (request.method !== 'GET') return;", $sw);
        $this->assertStringContainsString('if (url.origin !== self.location.origin) return;', $sw);
        // Network first: the saved copy is used only when fetching fails.
        $this->assertLessThan(strpos($sw, 'await cache.match(key)'), strpos($sw, 'response = await fetch(request);'));
        $this->assertStringContainsString("html.replace('data-offline-copy hidden', 'data-offline-copy data-offline-source=\"cache\"", $sw);
        // A ticket the server no longer shows this browser is not kept.
        $this->assertStringContainsString('[401, 403, 404, 410].includes(response.status)', $sw);
        $this->assertMatchesRegularExpression('/^\/tickets\/[0-9a-f-]{36}$/i', '/tickets/01a1d519-c0dc-7249-86b9-08e835265621');
        $this->assertDoesNotMatchRegularExpression('/^\/tickets\/[0-9a-f-]{36}$/i', '/tickets/01a1d519-c0dc-7249-86b9-08e835265621/wallet.pkpass');
    }

    public function test_signing_out_wipes_saved_ticket_copies(): void
    {
        $this->actingAs($this->attendee())->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertHeader('Clear-Site-Data', '"cache", "storage"');
    }

    public function test_wallet_section_text_is_readable_and_below_the_fold_images_are_lazy(): void
    {
        $html = $this->actingAs($this->attendee())->get(route('tickets.show', $this->order()))->assertOk()->getContent();
        // The fallback link has the page's text colour (not the browser's default blue on the dark ticket).
        $this->assertStringContainsString('style="color: var(--color-text-primary); text-decoration: underline;">My tickets</a>', $html);
        $this->assertStringContainsString('Adding a pass to a wallet doesn’t change your payment or admission.', $html);

        $unpaid = $this->actingAs($this->attendee())->get(route('tickets.show', $this->order(paid: false)))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<img src="[^"]+" alt="" width="180" height="135" loading="lazy" decoding="async"/', $unpaid);
        $this->assertStringNotContainsString('data-offline-copy data-offline-source', $unpaid);
    }

    public function test_wallet_status_lines_use_the_high_contrast_tokens(): void
    {
        $component = file_get_contents(resource_path('views/components/wallet-actions.blade.php'));
        $this->assertStringContainsString("'var(--color-warning-text)' : 'var(--color-success-text)'", $component);
        $this->assertStringContainsString('min-height: 44px', $component);
    }
}
