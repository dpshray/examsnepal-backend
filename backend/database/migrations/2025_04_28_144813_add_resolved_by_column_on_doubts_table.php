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
        Schema::table('doubts', function (Blueprint $table) {
            $table->unsignedBigInteger('solved_by')->after('id')->nullable();
            $table->foreign('solved_by')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doubts', function (Blueprint $table) {
            $table->dropForeign(['solved_by']);
            $table->dropColumn(['solved_by']);
        });
    }
};
