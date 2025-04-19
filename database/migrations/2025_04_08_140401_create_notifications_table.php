<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade'); // Admin who sent the notification
            $table->string('status')->default('unread'); // e.g., 'read', 'unread'
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        // Schema::dropIfExists('notification_user');
        Schema::dropIfExists('notifications');
    }
};
