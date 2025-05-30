<?php

use App\Models\ExamType;
use App\Models\StudentProfile;
use App\Models\SubscriptionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(StudentProfile::class)->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignIdFor(SubscriptionType::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->decimal('price',8,2);
            $table->decimal('paid',8,2);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('transaction_id');
            $table->dateTime('subscribed_at');
            $table->json('data');
            $table->boolean('status')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
