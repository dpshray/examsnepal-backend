<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7: automations may use channel "auto" (push if the app is installed,
 * else email, else SMS when allowed). SMS is expensive, so only automations
 * marked allow_sms (high-value moments) may fall back to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->boolean('allow_sms')->default(false)->after('channel');
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn('allow_sms');
        });
    }
};
