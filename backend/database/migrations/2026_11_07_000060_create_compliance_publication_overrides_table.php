<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Compliance administrator overrides of event publication gates (EventPublicationGates). Only adds a table. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_publication_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64)->index();
            $table->string('gate_code', 200);
            $table->text('gate_message');
            $table->text('reason');
            $table->foreignId('granted_by')->constrained('users');
            $table->timestamp('granted_at');
            $table->date('review_by');
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoke_reason')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'gate_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_publication_overrides');
    }
};
