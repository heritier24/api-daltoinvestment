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
        Schema::create('company_interests', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique(); // 'daily_investment' or 'referral_fee'
            $table->decimal('percentage', 5, 2); // e.g., 1.50 for 1.5%
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_interests');
    }
};
