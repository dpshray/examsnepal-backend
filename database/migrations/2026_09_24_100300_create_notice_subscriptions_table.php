<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notice_subscriptions', function (Blueprint $table) {
            $table->id();
            // Students live in student_profiles (the app's JWT "api" guard).
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->json('categories')->nullable();
            $table->json('exam_tags')->nullable();
            $table->enum('channel', ['email', 'push'])->default('email');
            $table->boolean('is_active')->default(true);
            $table->string('unsubscribe_token', 64)->unique();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_subscriptions');
    }
};
