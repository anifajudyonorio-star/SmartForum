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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_ID')->index();
            $table->string('Notification_Type')->nullable();
            $table->string('Notification_Title')->nullable();
            $table->text('Message')->nullable();
            $table->boolean('Is_Read')->default(false);
            $table->unsignedBigInteger('Post_ID')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
