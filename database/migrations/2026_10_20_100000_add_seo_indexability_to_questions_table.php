<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which public /mcq/{slug} pages search engines should index. Filled by
 * `php artisan mcq:score-seo` (App\Services\Seo\McqIndexability):
 * - seo_noindex_reason: why a page is kept out of the index (thin explanation,
 *   missing/garbled question text). NULL = indexable.
 * - canonical_question_id: set on exact duplicates, pointing at the copy that
 *   keeps the canonical URL; duplicates are left out of the sitemap.
 * Defaults keep every page indexable until scored, i.e. today's behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('seo_noindex_reason', 32)->nullable();
            $table->unsignedBigInteger('canonical_question_id')->nullable();
            $table->index(['seo_noindex_reason', 'canonical_question_id', 'id'], 'questions_seo_sitemap_index');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex('questions_seo_sitemap_index');
            $table->dropColumn(['seo_noindex_reason', 'canonical_question_id']);
        });
    }
};
