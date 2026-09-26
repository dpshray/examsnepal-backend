<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 needs automations that react to only some events (e.g. a submitted
 * FREE quiz) and recurring ones that run on a fixed day/hour (weekly report).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            // {"exam_type": "FREE_QUIZ"} or {"exam_type": ["SPRINT_QUIZ", "MOCK_TEST"]} - all keys must match, arrays mean "any of".
            $table->json('trigger_properties')->nullable()->after('trigger_event');
            // Scheduled automations only: {"weekdays": [0], "hours": [7]} in Asia/Kathmandu (0 = Sunday).
            $table->json('schedule')->nullable()->after('conditions');
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn(['trigger_properties', 'schedule']);
        });
    }
};
