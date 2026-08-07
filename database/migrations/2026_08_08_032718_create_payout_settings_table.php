<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payout_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('pool_percentage', 5, 2)->default(80.00);
            $table->timestamps();
        });

        DB::table('payout_settings')->insert([
            'pool_percentage' => 80.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_settings');
    }
};
