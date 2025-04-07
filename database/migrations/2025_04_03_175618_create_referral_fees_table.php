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
        Schema::create('referral_fees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id'); // The user who referred
            $table->unsignedBigInteger('referred_user_id'); // The user who was referred
            $table->unsignedBigInteger('transaction_id'); // The deposit transaction
            $table->decimal('deposit_amount', 15, 2); // The deposit amount
            $table->decimal('fee_amount', 15, 2); // The calculated referral fee
            $table->timestamps();

            $table->foreign('referrer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('referred_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_fees');
    }
};
