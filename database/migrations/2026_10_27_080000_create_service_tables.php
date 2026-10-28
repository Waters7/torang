<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('reporter_name');
            $table->string('reporter_contact');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('usage_log_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('category', 30);
            $table->string('title');
            $table->text('description');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('open');
            $table->string('source', 20);
            $table->foreignId('assigned_to')->nullable()->constrained('staff')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('rating_token_hash', 64)->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['category', 'priority']);
        });
        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('type', 20);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->index(['is_public', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('tickets');
    }
};
