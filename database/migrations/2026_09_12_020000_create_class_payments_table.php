<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('institute_student_id')->constrained('institute_students')->cascadeOnDelete();
            $table->string('transaction_uuid')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('payment_status')->default('PAYMENT_INIT');
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_payments');
    }
};
