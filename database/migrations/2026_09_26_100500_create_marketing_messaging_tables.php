<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lifecycle messaging engine (Phase 4, docs/marketing.md). Channel-agnostic
 * from day one: email now, push/SMS later through the same tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 150);
            $table->string('category', 20)->default('lifecycle'); // transactional | lifecycle | promotional
            $table->string('subject', 200);
            $table->string('preheader', 200)->nullable();
            $table->longText('html_body');
            $table->longText('text_body')->nullable(); // generated from html when empty
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_path', 255)->nullable(); // site path the CTA button links to; {{cta_url}}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 150);
            $table->string('channel', 10)->default('email'); // email | push | sms
            $table->string('trigger_type', 20); // event | scheduled
            $table->string('trigger_event', 50)->nullable()->index();
            $table->json('conditions')->nullable(); // StudentFilter keys, re-checked at send time
            $table->string('template_key', 80);
            $table->string('variant_b_template_key', 80)->nullable(); // A/B test
            $table->unsignedTinyInteger('ab_split_pct')->default(50); // % that gets variant A
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->string('goal_event', 50)->nullable();
            $table->json('goal_properties')->nullable(); // e.g. {"exam_type":"MOCK_TEST"}
            $table->unsignedInteger('cooldown_days')->default(30);
            $table->integer('priority')->default(50); // higher wins
            $table->boolean('is_upsell')->default(false); // never sent to paying students
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('message_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('automation_id')->nullable()->constrained('automations')->nullOnDelete();
            $table->unsignedBigInteger('broadcast_id')->nullable()->index();
            $table->string('channel', 10)->default('email');
            $table->string('category', 20)->default('lifecycle');
            $table->string('template_key', 80);
            $table->char('variant', 1)->default('A');
            // queued | sent | delivered | bounced | opened | clicked | failed | suppressed
            $table->string('status', 20)->default('queued');
            $table->string('suppress_reason', 50)->nullable();
            $table->string('to_address', 255)->nullable();
            $table->string('subject', 255)->nullable();
            $table->json('context')->nullable(); // trigger event properties
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable(); // student unsubscribed via this message
            $table->string('goal_event', 50)->nullable();
            $table->timestamp('goal_met_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
            $table->index(['student_id', 'sent_at']);
            $table->index(['automation_id', 'student_id']);
        });

        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->unique();
            $table->string('reason', 30); // unsubscribed | hard_bounce | complaint | manual
            $table->string('detail', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('marketing_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->json('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_settings');
        Schema::dropIfExists('suppressions');
        Schema::dropIfExists('message_sends');
        Schema::dropIfExists('automations');
        Schema::dropIfExists('email_templates');
    }
};
