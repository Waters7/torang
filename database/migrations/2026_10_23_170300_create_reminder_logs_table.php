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
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('remindable');
            $table->string('reminder_key', 50);
            $table->string('channel', 20);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['remindable_type', 'remindable_id', 'reminder_key', 'channel'], 'reminder_logs_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
    }
};
