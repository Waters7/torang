<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master pegawai dari DUK (Daftar Urut Kepangkatan). Sengaja tidak menyimpan
     * NIK, tempat/tanggal lahir, telepon, dan email pribadi.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 18)->unique();
            $table->string('name');
            $table->string('rank', 10)->nullable();
            $table->string('position')->nullable();
            $table->string('structural_position')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('placement')->nullable();
            $table->string('office_email')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('last_seen_period', 50)->nullable();
            $table->timestamps();
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->unique()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });

        Schema::dropIfExists('employees');
    }
};
