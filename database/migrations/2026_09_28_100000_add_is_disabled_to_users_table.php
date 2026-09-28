<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Admin can disable a teacher/corporate account: blocks login and hides it from the public classes listing.
            $table->boolean('is_disabled')->default(false)->after('role_id');
            $table->timestamp('disabled_at')->nullable()->after('is_disabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_disabled', 'disabled_at']);
        });
    }
};
