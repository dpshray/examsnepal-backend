<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Registry of official sites the notices pipeline fetches from. Sources are
// data (not code) so new ones can be added from the admin panel; `selectors`
// holds whatever the chosen fetch_type adapter needs (CSS selectors for
// html_list, field paths for json_api, ...).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notice_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('organization');
            $table->enum('category', ['loksewa', 'entrance', 'license']);
            $table->string('sub_category', 64);
            $table->string('province', 32)->nullable();
            $table->string('base_url');
            $table->string('list_url', 1024);
            $table->enum('fetch_type', ['html_list', 'rss', 'json_api', 'pdf_list', 'manual'])->default('html_list');
            $table->string('adapter_class')->nullable();
            $table->json('selectors')->nullable();
            $table->enum('language', ['ne', 'en', 'mixed'])->default('ne');
            $table->unsignedInteger('fetch_interval_minutes')->default(180);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_trusted')->default(true);
            $table->boolean('verify_ssl')->default(true);
            $table->unsignedSmallInteger('priority')->default(50);
            $table->text('notes')->nullable();

            // Health
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->unsignedInteger('last_item_count')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('consecutive_empty_runs')->default(0);
            $table->string('last_snapshot_hash', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('unhealthy_notified_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'fetch_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_sources');
    }
};
