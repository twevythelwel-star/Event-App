<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Database\Seeders\SystemAdminSeeder;
use Database\Seeders\UsLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Signs in as every profile and follows the links each one is shown, page by page. Nothing a profile is offered
 * may fail: no server errors, no link to a page that profile is refused, and no in-page link to a section that
 * is not there.
 */
class ProfileNavigationCrawlTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';
    private const MAX_PAGES = 70;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([UsLocationSeeder::class, FishFryEventsSeeder::class, DemoAccountsSeeder::class, SystemAdminSeeder::class, DemoOrdersSeeder::class]);
    }

    private function worker(string $role): User
    {
        $event = Event::allTenants()->findOrFail(self::EVENT);
        $user = User::create(['name' => "Crawl {$role}", 'email' => "{$role}@crawl.test", 'password' => bcrypt('x'), 'status' => 'active', 'email_verified_at' => now()]);
        UserRole::create(['user_id' => $user->id, 'role' => 'attendee', 'is_active' => true, 'assigned_at' => now()]);
        EventWorkerAssignment::create(['organizer_id' => $event->organizer_id, 'event_id' => $event->id, 'user_id' => $user->id,
            'worker_role' => $role, 'station_assignment' => 'all', 'status' => 'active', 'is_active' => true,
            'starts_at' => now()->subHour(), 'expires_at' => now()->addDay(),
            'custom_permissions' => $role === 'custom_restricted_worker' ? ['scan_qr_tickets'] : null]);

        return $user;
    }

    /** @return array<string, array<int, string>> problems found, by page */
    private function crawl(?User $user): array
    {
        $queue = ['/', '/events', '/settings', '/attendee/dashboard'];
        $seen = [];
        $problems = [];
        $eventPages = 0;

        while ($queue && count($seen) < self::MAX_PAGES) {
            [$url, $from] = is_array($queue[0]) ? array_shift($queue) : [array_shift($queue), null];
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            $response = $user ? $this->actingAs($user)->get($url) : $this->get($url);
            $status = $response->getStatusCode();
            if ($status >= 500 || ($from !== null && in_array($status, [403, 404], true))) {
                $problems[$url][] = "HTTP {$status}" . ($from ? " (linked from {$from})" : '');
                continue;
            }
            if ($status !== 200 || !str_contains((string) $response->headers->get('content-type'), 'html')) {
                continue;
            }

            $html = $response->getContent();
            preg_match_all('/\sid="([^"]+)"/', $html, $ids);
            $ids = array_flip($ids[1]);
            preg_match_all('/href="([^"]+)"/', $html, $links);
            foreach (array_unique($links[1]) as $href) {
                $href = html_entity_decode($href);
                if (str_starts_with($href, '#')) {
                    if ($href !== '#' && !isset($ids[substr($href, 1)])) {
                        $problems[$url][] = "link to missing section {$href}";
                    }
                    continue;
                }
                $href = preg_replace('#^https?://(localhost|127\.0\.0\.1)(:\d+)?#', '', $href);
                if (!str_starts_with($href, '/') || str_starts_with($href, '//')
                    || preg_match('#^/(css|js|images|icons|storage|favicon|site\.webmanifest|build|livewire|e/|media)#', $href)
                    || str_contains($href, 'logout') || str_contains($href, '/qr/')) {
                    continue;
                }
                $path = explode('#', $href, 2)[0];
                // The public event pages all share one template: a few are enough.
                if (preg_match('#^/events/evt-[^/]+$#', $path) && $path !== '/events/' . self::EVENT && $eventPages++ > 2) {
                    continue;
                }
                if (!isset($seen[$path])) {
                    $queue[] = [$path, $url];
                }
            }
        }

        $this->assertGreaterThan(3, count($seen), 'the crawl reached pages');

        return $problems;
    }

    public function test_every_profile_only_sees_links_that_work(): void
    {
        $actors = [
            'guest' => null,
            'attendee' => User::where('email', 'attendee@islandevents.example')->firstOrFail(),
            'host' => User::where('email', 'host@islandevents.example')->firstOrFail(),
            'admin' => User::where('email', 'admin@islandevents.example')->firstOrFail(),
            'system admin' => User::where('email', config('events.system_admin.email'))->firstOrFail(),
        ];
        foreach (['payment_clerk', 'ticket_checker', 'food_station_worker', 'event_supervisor', 'custom_restricted_worker'] as $role) {
            $actors[$role] = $this->worker($role);
        }

        $all = [];
        foreach ($actors as $name => $user) {
            auth()->logout();
            $this->flushSession();
            foreach ($this->crawl($user) as $url => $problems) {
                $all[] = "{$name}: {$url}: " . implode('; ', $problems);
            }
        }

        $this->assertSame([], $all, "Broken navigation:\n" . implode("\n", $all));
    }
}
