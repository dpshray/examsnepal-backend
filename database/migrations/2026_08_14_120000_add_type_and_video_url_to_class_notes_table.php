<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_notes', function (Blueprint $table) {
            $table->enum('type', ['pdf', 'video_link'])->default('pdf')->after('title');
            $table->string('video_url')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('class_notes', function (Blueprint $table) {
            $table->dropColumn(['type', 'video_url']);
        });
    }
};
