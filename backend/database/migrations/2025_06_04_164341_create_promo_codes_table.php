<?php

use App\Models\ExamType;
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
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->decimal('discount_percent',5,2);
            $table->text('detail')->nullable();
            $table->boolean('status')->default(1);
            $table->timestamps();
        });

        if (Schema::hasTable('promo_codes')) {
            DB::table('promo_codes')->insert([
                [
                    'code' => 'DWORK2025',
                    'discount_percent' => 5,
                    'detail' => 'DWORK FESTIVAL'
                ],[
                    'code' => 'KATHMANDUWEAR2081',
                    'discount_percent' => 2,
                    'detail' => 'KATHMANDU WEAR 2081'
                ],[
                    'code' => 'DASHAIN-2081',
                    'discount_percent' => 3,
                    'detail' => 'DASHAIN 2081'
                ],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
