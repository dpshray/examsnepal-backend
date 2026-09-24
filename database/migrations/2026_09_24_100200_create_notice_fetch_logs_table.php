<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notice_fetch_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('notice_sources')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('items_found')->default(0);
            $table->unsignedInteger('items_new')->default(0);
            $table->enum('status', ['running', 'success', 'empty', 'failed', 'skipped'])->default('running');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_fetch_logs');
    }
};
