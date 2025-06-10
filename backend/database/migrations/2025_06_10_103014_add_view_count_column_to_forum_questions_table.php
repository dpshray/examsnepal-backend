<?php

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
        Schema::table('forum_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('view_count')->after('id')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forum_questions', function (Blueprint $table) {
            $table->dropColumn('view_count');
        });
    }
};
