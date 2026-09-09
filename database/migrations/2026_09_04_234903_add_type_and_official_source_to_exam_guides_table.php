<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds the two fields the new 7-field taxonomy (Medical/Paramedical/
// Engineering/Management/Agriculture/Law/Others x License/Loksewa/Entrance/
// Job) needs that the original schema didn't carry: which of those 4 types
// a given exam is, and a citation link to its governing body's official site
// (required at the bottom of every exam page per the content-accuracy pass).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_guides', function (Blueprint $table) {
            $table->enum('type', ['license', 'loksewa', 'entrance', 'job'])
                ->default('entrance')
                ->after('exam_category_id');
            $table->string('official_source')->nullable()->after('conducting_body');
        });
    }

    public function down(): void
    {
        Schema::table('exam_guides', function (Blueprint $table) {
            $table->dropColumn(['type', 'official_source']);
        });
    }
};
