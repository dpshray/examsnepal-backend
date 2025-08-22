<?php

use App\Models\ExamType;
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
        Schema::create('subscription_types', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ExamType::class)->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('duration')->comment('month');
            $table->decimal('price',8,2);
            $table->boolean('status')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_types');
    }
};
