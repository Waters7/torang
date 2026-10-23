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
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('group', 20)->index();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10);
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('category')->nullable();
            $table->string('isp')->nullable();
            $table->string('bandwidth', 50)->nullable();
            $table->string('isp_contact')->nullable();
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Pengaturan per monitor; null = pakai pengaturan global.
            $table->unsignedInteger('interval_seconds')->nullable();
            $table->unsignedSmallInteger('timeout_seconds')->nullable();
            $table->unsignedTinyInteger('packet_count')->nullable();
            $table->unsignedTinyInteger('failure_threshold')->nullable();
            $table->unsignedInteger('latency_warning_ms')->nullable();
            $table->json('expected_status_codes')->nullable();
            $table->string('keyword')->nullable();
            $table->boolean('check_ssl')->default(false);

            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Status terkini
            $table->string('current_status', 20)->default('unknown');
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->timestamp('last_status_change_at')->nullable();
            $table->unsignedInteger('last_latency_ms')->nullable();
            $table->decimal('last_packet_loss', 5, 2)->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamp('degraded_notified_at')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->timestamps();

            $table->index(['group', 'is_active', 'sort_order']);
        });

        Schema::create('monitor_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at');
            $table->string('status', 20);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->decimal('packet_loss', 5, 2)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error')->nullable();

            $table->index(['monitor_id', 'checked_at']);
            $table->index('checked_at');
        });

        Schema::create('monitor_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('notes')->nullable();
            // Untuk cooldown: notifikasi pulih hanya dikirim bila notifikasi offline terkirim.
            $table->timestamp('down_notified_at')->nullable();
            $table->timestamp('up_notified_at')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'started_at']);
        });

        Schema::create('monitor_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('checks_total')->default(0);
            $table->unsignedInteger('checks_up')->default(0);
            $table->unsignedInteger('checks_degraded')->default(0);
            $table->unsignedInteger('checks_down')->default(0);
            $table->unsignedInteger('avg_latency_ms')->nullable();
            $table->decimal('uptime_percent', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['monitor_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitor_daily_stats');
        Schema::dropIfExists('monitor_incidents');
        Schema::dropIfExists('monitor_checks');
        Schema::dropIfExists('monitors');
    }
};
