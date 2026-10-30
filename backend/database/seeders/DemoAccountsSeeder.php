<?php

namespace Database\Seeders;

use App\Models\OrganizerUser;
use App\Models\User;
use App\Models\AdminPermission;
use App\Models\HostPaymentPolicy;
use App\Models\UserRole;
use Illuminate\Database\Seeder;

/**
 * Local development sign-in accounts, one per account type.
 * Never run in production: these share a well-known password.
 */
class DemoAccountsSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'IslandDemo2026!';

    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $accounts = [
            ['email' => 'attendee@islandevents.example', 'name' => 'Demo Attendee', 'role' => null, 'organizer' => null],
            ['email' => 'host@islandevents.example', 'name' => 'Demo Event Host', 'role' => 'event_host', 'organizer' => 'org-kingston-youth'],
            ['email' => 'admin@islandevents.example', 'name' => 'Demo Delegated Admin', 'role' => 'admin', 'organizer' => null],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(['email' => $account['email']], [
                'name' => $account['name'],
                'phone' => '+1 (876) 555-0199',
                'password' => password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            UserRole::updateOrCreate(['user_id' => $user->id, 'role' => 'attendee'], ['is_active' => true, 'assigned_at' => now()]);

            if ($account['role']) {
                UserRole::updateOrCreate(['user_id' => $user->id, 'role' => $account['role']], ['is_active' => true, 'assigned_at' => now()]);
            }

            // The demo administrator gets oversight permissions across organizers, but no audit-log
            // access and no payment verification, so the console's permission limits are visible.
            if ($account['role'] === 'admin') {
                AdminPermission::updateOrCreate(['user_id' => $user->id], [
                    'scope' => 'all',
                    'can_manage_events' => true,
                    'can_moderate_content' => true,
                    'can_support_orders' => true,
                    'can_view_payments' => true,
                    'can_view_reports' => true,
                    'can_access_audit_logs' => false,
                    'assigned_organizer_ids' => [],
                ]);
            }

            if ($account['organizer']) {
                OrganizerUser::updateOrCreate(
                    ['organizer_id' => $account['organizer'], 'user_id' => $user->id],
                    ['role' => 'event_owner', 'is_active' => true]
                );
            }
        }

        // The demo host takes bank transfers and cash, so both checkout paths can be tried.
        // Placeholder account details: never real banking information.
        if (\App\Models\Organizer::whereKey('org-kingston-youth')->exists()) {
            HostPaymentPolicy::updateOrCreate(['organizer_id' => 'org-kingston-youth'], [
                'enable_cash' => true,
                'bank_transfer_instructions' => "DEMO ONLY: not a real account\nBank: Example Bank Jamaica\nAccount name: Kingston Youth (demo)\nAccount number: 000-000-000",
                'require_transfer_reference' => true,
                'require_sender_name' => false,
            ]);

            // Sample ticket types for the demo Kingston event, so the ticket step can be tried.
            // Same shape the host event editor writes (priceJMD, availableStock, maxPerCustomer).
            // Only added when the host hasn't configured any, and not under tests, whose host fixtures
            // expect this event to start with no ticket types (checkout tests build their own tiers).
            $kingston = \App\Models\Event::withoutGlobalScopes()->find('evt-kingston-harbour-fry');
            if ($kingston && empty($kingston->ticket_tiers) && !app()->environment('testing')) {
                $kingston->ticket_tiers = [
                    ['id' => 'tier-ga', 'name' => 'General Admission', 'description' => 'Entry to the waterfront grounds and live stage.', 'priceJMD' => 2250, 'priceUSD' => 15, 'availableStock' => 200, 'maxPerCustomer' => 6],
                    ['id' => 'tier-vip', 'name' => 'VIP Waterfront Lounge', 'description' => 'Express entry and reserved seating.', 'priceJMD' => 6800, 'priceUSD' => 45, 'availableStock' => 45, 'maxPerCustomer' => 4],
                ];
                $kingston->save();
            }
        }
    }
}
