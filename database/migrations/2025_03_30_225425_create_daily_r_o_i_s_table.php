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
        Schema::create('daily_r_o_i_s', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('deposit_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2); // The daily ROI amount
            $table->date('date'); // The date of the ROI calculation
            $table->timestamps();

            $table->index(['user_id', 'deposit_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_r_o_i_s');
    }
};
