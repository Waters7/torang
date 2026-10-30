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
        // Mode maintenance per monitor: pengecekan & notifikasi berhenti, publik melihat "Maintenance".
        Schema::table('monitors', function (Blueprint $table) {
            $table->string('maintenance_note')->nullable()->after('ssl_expires_at');
            $table->timestamp('maintenance_started_at')->nullable()->after('maintenance_note');
            $table->timestamp('maintenance_until')->nullable()->after('maintenance_started_at');
        });

        // SSID yang dipilih petugas untuk tampil (beserta password) di halaman depan; default tidak tampil.
        Schema::table('wifi_networks', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wifi_networks', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['maintenance_note', 'maintenance_started_at', 'maintenance_until']);
        });
    }
};
