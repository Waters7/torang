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
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('website')->nullable();
            $table->string('panel_url', 2048)->nullable();
            $table->string('support_contact')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('hosted_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type', 30);
            $table->string('identifier');
            $table->string('account_email')->nullable();
            $table->string('used_by')->nullable();
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('last_paid_at')->nullable();
            $table->date('expires_at');
            $table->string('billing_cycle', 20);
            $table->decimal('cost', 15, 2)->default(0);
            $table->boolean('auto_renew')->default(false);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });

        Schema::create('service_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hosted_service_id')->constrained()->restrictOnDelete();
            $table->date('paid_at');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 15, 2);
            $table->string('proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hosted_service_id', 'paid_at']);
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_payments');
        Schema::dropIfExists('hosted_services');
        Schema::dropIfExists('providers');
    }
};
