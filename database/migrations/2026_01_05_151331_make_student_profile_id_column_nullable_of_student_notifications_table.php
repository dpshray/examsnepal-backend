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
        Schema::table('student_notifications', function (Blueprint $table) {
            // 1️⃣ Drop foreign key constraint
            $table->dropForeign(['student_profile_id']);

            // 2️⃣ Make column nullable
            $table->unsignedBigInteger('student_profile_id')
                ->nullable()
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_notifications', function (Blueprint $table) {
            // 1️⃣ Make column NOT NULL again
            $table->unsignedBigInteger('student_profile_id')
                ->nullable(false)
                ->change();

            // 2️⃣ Re-add foreign key
            $table->foreign('student_profile_id')
                ->references('id')
                ->on('student_profiles')
                ->onDelete('cascade');
        });
    }
};
