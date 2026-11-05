<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // The dish's tradition: a key from config/traditions.php, the list hosts also use for events
            $table->string('cuisine', 100)->nullable()->after('category');

            // Dietary tags (vendor-confirmed only: vegetarian, vegan, halal, kosher, etc.)
            $table->json('dietary_tags')->nullable()->after('description');

            // Allergen disclosures (vendor-provided information)
            $table->json('allergens')->nullable()->after('dietary_tags');

            // Spice level (mild, medium, hot, extra_hot, or null if not applicable)
            $table->string('spice_level', 32)->nullable()->after('allergens');

            // Portion sizes configuration (e.g., small, medium, large with prices)
            $table->json('portion_sizes')->nullable()->after('spice_level');

            // Vendor or food-station assignment for fulfillment
            $table->string('vendor_assignment', 100)->nullable()->after('sort_order');

            // Item-specific preparation notes
            $table->text('preparation_notes')->nullable()->after('vendor_assignment');

            // Index for filtering by cuisine
            $table->index('cuisine');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['cuisine']);
            $table->dropColumn([
                'cuisine',
                'dietary_tags',
                'allergens',
                'spice_level',
                'portion_sizes',
                'vendor_assignment',
                'preparation_notes',
            ]);
        });
    }
};
