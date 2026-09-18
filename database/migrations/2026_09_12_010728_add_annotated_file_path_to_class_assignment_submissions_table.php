<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_assignment_submissions', function (Blueprint $table) {
            // The teacher's pen-marked-up copy of a PDF submission, kept
            // separate from file_path so the student's original upload is
            // never overwritten/lost.
            $table->string('annotated_file_path')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('class_assignment_submissions', function (Blueprint $table) {
            $table->dropColumn('annotated_file_path');
        });
    }
};
