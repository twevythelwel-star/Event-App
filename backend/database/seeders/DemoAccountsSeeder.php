<?php

namespace Database\Seeders;

use App\Models\OrganizerUser;
use App\Models\User;
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

            if ($account['organizer']) {
                OrganizerUser::updateOrCreate(
                    ['organizer_id' => $account['organizer'], 'user_id' => $user->id],
                    ['role' => 'event_owner', 'is_active' => true]
                );
            }
        }
    }
}
