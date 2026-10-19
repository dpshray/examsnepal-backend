<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Result of posting a notice to the ExamsNepal Facebook Page as an image post
 * (App\Services\Notices\FacebookNoticePoster). facebook_post_id doubles as the
 * "already posted" guard so a notice is never posted twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->string('facebook_post_id')->nullable();
            $table->timestamp('facebook_posted_at')->nullable();
            $table->string('facebook_error', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->dropColumn(['facebook_post_id', 'facebook_posted_at', 'facebook_error']);
        });
    }
};
