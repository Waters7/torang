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
        Schema::create('wifi_networks', function (Blueprint $table) {
            $table->id();
            $table->string('ssid', 64);
            $table->string('location');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('access_point')->nullable();
            $table->string('audience', 20);
            $table->string('security', 20);
            // Terenkripsi (cast "encrypted"); tidak pernah di-log, di-cache, atau dikirim ke halaman publik.
            $table->text('password')->nullable();
            $table->unsignedSmallInteger('vlan')->nullable();
            $table->unsignedSmallInteger('rotation_days')->nullable();
            $table->date('password_changed_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'ssid']);
        });

        Schema::create('wifi_password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wifi_network_id')->constrained()->cascadeOnDelete();
            // Password lama (terenkripsi) yang diganti pada changed_at.
            $table->text('password');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['wifi_network_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wifi_password_histories');
        Schema::dropIfExists('wifi_networks');
    }
};
