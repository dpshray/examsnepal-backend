<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('username');
        });

        // Backfill a clean, URL-safe slug for existing corporate (institute) accounts.
        // Other roles don't have a public link yet, so they're left null for now.
        User::whereHas('role', fn ($q) => $q->where('name', 'corporate'))
            ->whereNull('slug')
            ->orderBy('id')
            ->each(function (User $user) {
                $user->slug = User::generateUniqueSlug($user->username ?: $user->fullname ?: "institute-{$user->id}");
                $user->saveQuietly();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
