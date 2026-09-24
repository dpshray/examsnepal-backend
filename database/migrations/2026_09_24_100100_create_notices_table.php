<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->nullable()->constrained('notice_sources')->nullOnDelete();
            $table->enum('category', ['loksewa', 'entrance', 'license']);
            $table->string('sub_category', 64)->nullable();
            $table->string('organization');
            $table->string('province', 32)->nullable();

            $table->text('title_original');
            $table->string('title_en', 500)->nullable();
            $table->string('title_ne', 500)->nullable();
            $table->string('slug', 191)->unique();
            $table->text('summary_en')->nullable();
            $table->text('summary_ne')->nullable();

            $table->string('source_url', 1024);
            $table->json('attachment_urls')->nullable();

            $table->string('published_date_bs', 10)->nullable();
            $table->date('published_date_ad')->nullable();
            $table->date('application_start_ad')->nullable();
            $table->date('application_deadline_ad')->nullable();
            $table->date('double_fee_deadline_ad')->nullable();
            $table->date('exam_date_ad')->nullable();
            $table->string('exam_date_bs', 10)->nullable();

            $table->enum('notice_type', [
                'vacancy', 'exam_schedule', 'result', 'admit_card', 'syllabus',
                'interview', 'entrance_form', 'license_exam', 'other',
            ])->default('other');

            $table->json('posts')->nullable();
            $table->json('fees')->nullable();
            $table->json('eligibility')->nullable();
            $table->json('exam_centers')->nullable();
            $table->json('exam_tags')->nullable();

            $table->char('content_hash', 64)->unique();
            $table->decimal('ai_confidence', 3, 2)->nullable();
            $table->string('ai_model', 64)->nullable();
            $table->json('ai_raw')->nullable();
            $table->unsignedInteger('ai_input_tokens')->nullable();
            $table->unsignedInteger('ai_output_tokens')->nullable();
            $table->timestamp('enriched_at')->nullable();
            $table->string('enrichment_error', 500)->nullable();

            $table->enum('status', ['pending', 'published', 'rejected', 'archived'])->default('pending');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['category', 'status', 'published_date_ad']);
            $table->index('application_deadline_ad');
            $table->index('exam_date_ad');
            $table->index(['status', 'published_date_ad']);
            $table->index(['organization', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
