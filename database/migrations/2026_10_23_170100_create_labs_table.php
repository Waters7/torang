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
        Schema::create('labs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('capacity')->default(0);
            $table->string('color', 7)->default('#04B6A3');
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('photo_path')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_device_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained()->cascadeOnDelete();
            $table->string('device_type', 30);
            $table->unsignedSmallInteger('total')->default(0);
            $table->unsignedSmallInteger('good')->default(0);
            $table->unsignedSmallInteger('broken')->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['lab_id', 'device_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_device_counts');
        Schema::dropIfExists('labs');
    }
};
