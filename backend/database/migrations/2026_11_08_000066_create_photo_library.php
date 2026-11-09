<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The event photography library: approved photographs of each kind of event (reggae concert, Jamaican fish fry,
 * family picnic, wedding, …), shown as a clearly labelled illustrative photo on events whose host has not uploaded
 * their own flyer. Files live in storage; the table holds references, provenance, licence and consent records,
 * focal points, alt text and review.
 *
 * Events get the kind of event they are (photo_subject), chosen by the host, so a photo is matched to what the
 * event actually is and never guessed from its title.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_photos', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 60)->index();          // config('photo_library.subjects') key
            $table->string('title', 160);
            $table->string('alt_text', 300);
            $table->string('path');                            // private disk, tenants/platform/library/…
            $table->string('mime', 40);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('size_bytes');
            $table->json('variants')->nullable();              // responsive WebP/AVIF sizes
            $table->unsignedTinyInteger('focal_x')->default(50);
            $table->unsignedTinyInteger('focal_y')->default(50);
            // Provenance: where the photograph came from, under what licence, and consent for people shown.
            $table->string('source', 20);                      // generated | licensed | commissioned
            $table->string('source_detail', 255)->nullable();  // e.g. the generator, photographer or stock library
            $table->string('source_url', 500)->nullable();
            $table->string('licence', 160);
            $table->string('licence_url', 500)->nullable();
            $table->boolean('identifiable_people')->default(true);
            $table->string('model_release', 20);               // on_file | not_needed | none
            $table->json('standards_checked')->nullable();     // the photography standards the reviewer confirmed
            $table->string('status', 20)->default('pending_review')->index(); // pending_review | approved | rejected | retired
            $table->foreignId('uploaded_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['subject', 'status']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('photo_subject', 60)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('photo_subject'));
        Schema::dropIfExists('library_photos');
    }
};
