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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone_number')->unique();
            $table->string('password');
            $table->string('promocode')->unique()->nullable();
            $table->enum('role', ['user_client', 'admin', 'super_admin'])->default('user_client');
            $table->string('usdt_wallet')->nullable();
            $table->unsignedBigInteger('referred_by')->nullable();
            $table->foreign('referred_by')->references('id')->on('users')->onDelete('set null');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
