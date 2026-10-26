<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use App\Models\UserRole;
use App\Services\DisputeService;
use App\Services\HostApplicationService;
use Illuminate\Database\Seeder;

/**
 * Local development review queue for administrators: one pending Event Host application (from a demo
 * applicant account) and one open dispute filed by the demo attendee. Never run in production.
 */
class DemoSupportRequestsSeeder extends Seeder
{
    public function run(DisputeService $disputes): void
    {
        if (app()->environment('production')) {
            return;
        }

        $applicant = User::updateOrCreate(['email' => 'applicant@islandevents.example'], [
            'name' => 'Demo Host Applicant',
            'phone' => '+1 (876) 555-0155',
            'password' => password_hash(DemoAccountsSeeder::DEMO_PASSWORD, PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        UserRole::updateOrCreate(['user_id' => $applicant->id, 'role' => 'attendee'], ['is_active' => true, 'assigned_at' => now()]);

        HostApplicationService::submitApplication($applicant, [
            'organization_name' => 'Treasure Beach Sunset Collective',
            'description' => 'Monthly beachside fish fry and live music nights supporting the local fishing co-op.',
            'country' => 'JM',
            'contact_phone' => '+1 (876) 555-0155',
            'website_or_social' => 'instagram.com/treasurebeachsunset',
        ]);

        $attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();
        $order = Order::withoutGlobalScopes()
            ->where('user_id', $attendee->id)
            ->where('status', 'payment_submitted')
            ->firstOrFail();

        $disputes->submit($attendee, [
            'order_id' => $order->id,
            'dispute_type' => 'payment_mismatch',
            'reason' => 'I sent the bank transfer two days ago but my ticket still shows payment pending.',
            'amount' => (float) $order->total_amount,
        ]);
    }
}
