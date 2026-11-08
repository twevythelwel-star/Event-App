<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SystemAdminSeeder::class,
            UsLocationSeeder::class,
            ComplianceDatabaseSeeder::class,
            CulturalCatalogSeeder::class,
            FishFryEventsSeeder::class,
            DemoAccountsSeeder::class,
            DemoOrdersSeeder::class,
            DemoSupportRequestsSeeder::class,
        ]);
    }
}
