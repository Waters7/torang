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
        // Audit log & tab "Log akses" diurutkan per waktu dan tabelnya terus bertambah.
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index('created_at');
        });

        // Insiden yang masih terbuka dihitung di dashboard, Mode TV, dan saat maintenance dimulai.
        Schema::table('monitor_incidents', function (Blueprint $table) {
            $table->index(['monitor_id', 'resolved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitor_incidents', function (Blueprint $table) {
            $table->dropIndex(['monitor_id', 'resolved_at']);
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
