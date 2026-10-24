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
        Schema::create('exam_organizers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('recipient_title_default');
            $table->string('responsible_party');
            $table->string('bank_account_name');
            $table->string('bank_name', 100);
            $table->string('bank_account_number', 50);
            $table->string('bank_account_holder');
            $table->string('budget_name_default');
            $table->string('guideline_year_default', 4);
            $table->decimal('rate_cbt_per_unit', 15, 2)->nullable();
            $table->decimal('rate_meal_per_person_day', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50);
            $table->unsignedInteger('version');
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['key', 'version']);
        });

        Schema::create('ukom_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exam_organizer_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft');

            // Kegiatan
            $table->string('exam_name');
            $table->unsignedSmallInteger('period');
            $table->string('study_program');
            $table->date('exam_date');
            $table->string('activity_name', 500);

            // Surat
            $table->string('letter_number');
            $table->unsignedInteger('letter_sequence')->nullable();
            $table->unsignedSmallInteger('letter_year');
            $table->string('letter_place', 100);
            $table->date('letter_date');
            $table->unsignedTinyInteger('attachment_count');
            $table->text('subject');
            $table->string('recipient_title', 500);
            $table->string('recipient_org');
            $table->string('recipient_place', 100);

            // Penanda tangan & pernyataan (salinan saat laporan dibuat)
            $table->string('signer_name');
            $table->string('signer_nip', 18);
            $table->string('signer_position');
            $table->string('signer_label', 100);
            $table->string('signer_address_1');
            $table->string('signer_address_2')->nullable();
            $table->string('responsible_role');
            $table->string('budget_name');
            $table->string('guideline_year', 4);
            $table->string('responsible_party');
            $table->string('bank_account_name');
            $table->string('bank_name', 100);
            $table->string('bank_account_number', 50);
            $table->string('bank_account_holder');

            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('docx_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_error')->nullable();
            $table->string('signed_scan_path')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['letter_year', 'letter_sequence']);
            $table->index(['status', 'letter_year']);
            $table->index('exam_date');
        });

        Schema::create('ukom_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ukom_report_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('description');
            $table->unsignedInteger('qty_1');
            $table->string('unit_1', 50);
            $table->unsignedInteger('qty_2')->nullable();
            $table->string('unit_2', 50)->nullable();
            $table->string('note')->nullable();
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->string('display_text', 500);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ukom_report_items');
        Schema::dropIfExists('ukom_reports');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('exam_organizers');
    }
};
