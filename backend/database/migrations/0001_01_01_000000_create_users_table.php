<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform user accounts with authentication, 2FA, and status.
     * Runs first because organizer, role, and worker tables reference users.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 32)->nullable()->index();
            $table->string('password');
            $table->string('status', 24)->default('active'); // active, suspended, pending_verification
            $table->string('preferred_display_currency', 4)->default('JMD');
            $table->string('current_context_role', 24)->default('attendee');
            $table->boolean('is_system_admin')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'current_context_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
