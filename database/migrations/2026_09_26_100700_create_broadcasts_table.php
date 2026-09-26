<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-off sends to a segment (Phase 6). Recipients are resolved when the
 * broadcast becomes due and go through the same SendGuard as automations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('template_key', 80);
            $table->json('filters'); // StudentFilter keys (may include segment_id)
            $table->string('status', 20)->default('scheduled'); // scheduled | queued | cancelled
            $table->timestamp('scheduled_for');
            $table->unsignedInteger('recipients_queued')->nullable();
            $table->unsignedInteger('recipients_skipped')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
