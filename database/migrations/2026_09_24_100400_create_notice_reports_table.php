<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Report an error" submissions from notice detail pages - the admin panel
// has no generic ticket system, so these get their own small queue.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notice_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained('notices')->cascadeOnDelete();
            $table->string('field', 64)->nullable();
            $table->text('message');
            $table->string('email')->nullable();
            $table->string('ip', 45)->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_reports');
    }
};
