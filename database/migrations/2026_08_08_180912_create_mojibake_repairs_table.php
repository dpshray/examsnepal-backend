<?php

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
        Schema::create('mojibake_repairs', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 36)->index();
            $table->string('table_name');
            $table->string('column_name');
            $table->unsignedBigInteger('row_id');
            $table->longText('old_value');
            $table->longText('new_value');
            $table->timestamps();

            $table->index(['table_name', 'column_name', 'row_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mojibake_repairs');
    }
};
