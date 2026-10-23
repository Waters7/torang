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
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('nip', 30)->nullable();
            $table->string('position')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('telegram_chat_id', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('duty_weeks', function (Blueprint $table) {
            $table->id();
            $table->date('week_start')->unique();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('duty_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('duty_week_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            // lab_id NULL = seluruh CBT Center. NULL tidak dianggap sama di index unik,
            // jadi keunikan memakai kolom turunan scope_key. MySQL tidak mengizinkan
            // cascade/set null pada FK yang dipakai kolom turunan, maka restrict.
            $table->foreignId('lab_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('scope_key')->storedAs('COALESCE(lab_id, 0)');
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['date', 'shift_id', 'scope_key', 'staff_id'], 'duty_assignments_slot_unique');
            $table->index(['date', 'shift_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duty_assignments');
        Schema::dropIfExists('duty_weeks');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('staff');
    }
};
