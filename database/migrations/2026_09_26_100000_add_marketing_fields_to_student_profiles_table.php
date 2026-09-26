<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing/lifecycle fields (docs/marketing.md). student_profiles never had
 * a real signup timestamp - created_at is added here and backfilled from the
 * legacy `date` string by `php artisan marketing:backfill`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('student_profiles', 'created_at')) {
                $table->timestamp('created_at')->nullable()->index();
            }
            $table->date('target_exam_date')->nullable();
            $table->string('signup_source', 50)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->timestamp('last_active_at')->nullable()->index();
            $table->string('last_platform', 10)->nullable(); // web | android | ios
            $table->boolean('marketing_email_opt_in')->default(true);
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('timezone', 50)->default('Asia/Kathmandu');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropIndex(['last_active_at']);
            $table->dropColumn([
                'target_exam_date', 'signup_source', 'utm_source', 'utm_medium', 'utm_campaign',
                'last_active_at', 'last_platform', 'marketing_email_opt_in', 'unsubscribed_at', 'timezone',
            ]);
        });
    }
};
