<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE class_notes MODIFY type ENUM('pdf', 'video_link', 'image') NOT NULL DEFAULT 'pdf'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE class_notes MODIFY type ENUM('pdf', 'video_link') NOT NULL DEFAULT 'pdf'");
    }
};
