<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('classes')
            ->whereNotNull('syllabus')
            ->where('syllabus', '!=', '')
            ->orderBy('id')
            ->get()
            ->each(function ($class) {
                DB::table('class_syllabus_topics')->insert([
                    'class_id' => $class->id,
                    'title' => 'Overview',
                    'description' => $class->syllabus,
                    'order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('syllabus');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->longText('syllabus')->nullable();
        });
    }
};
