<?php

namespace App\Models;

use App\Enums\UkomReportStatus;
use Database\Factories\UkomReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Laporan pertanggungjawaban biaya operasional UKOM (surat pengantar, laporan penggunaan dana, SPTJM).
 * Data kolegium & penanda tangan disalin ke laporan agar laporan lama tidak berubah saat master diubah.
 * "Diarsipkan" = soft delete; nomor surat tidak pernah dipakai ulang.
 */
#[Fillable([
    'booking_id', 'exam_organizer_id', 'document_template_id', 'status', 'exam_name', 'period', 'study_program',
    'exam_date', 'activity_name', 'letter_number', 'letter_sequence', 'letter_year', 'letter_place', 'letter_date',
    'attachment_count', 'subject', 'recipient_title', 'recipient_org', 'recipient_place', 'signer_name', 'signer_nip',
    'signer_position', 'signer_label', 'signer_address_1', 'signer_address_2', 'responsible_role', 'budget_name',
    'guideline_year', 'responsible_party', 'bank_account_name', 'bank_name', 'bank_account_number',
    'bank_account_holder', 'total_amount', 'docx_path', 'pdf_path', 'pdf_error', 'signed_scan_path', 'finalized_at',
    'created_by',
])]
class UkomReport extends Model
{
    /** @use HasFactory<UkomReportFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UkomReportStatus::class,
            'period' => 'integer',
            'exam_date' => 'date',
            'letter_sequence' => 'integer',
            'letter_year' => 'integer',
            'letter_date' => 'date',
            'attachment_count' => 'integer',
            'total_amount' => 'decimal:2',
            'finalized_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('ukom')
            ->logOnly(['status', 'letter_number', 'activity_name', 'total_amount', 'signer_name', 'signed_scan_path'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return HasMany<UkomReportItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(UkomReportItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<ExamOrganizer, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(ExamOrganizer::class, 'exam_organizer_id');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    /**
     * @return BelongsTo<DocumentTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }

    /**
     * Folder dokumen di disk privat: reports/ukom/{id}.
     */
    public function documentDirectory(): string
    {
        return "reports/ukom/{$this->getKey()}";
    }

    /**
     * Nama file tanpa ekstensi, mis. "laporan-ukom-uji-kompetensi-nasional-periode-2-mahasiswa-d-3-teknologi-bank-darah".
     */
    public function fileBaseName(): string
    {
        return 'laporan-ukom-'.Str::limit(Str::slug($this->activity_name), 90, '');
    }
}
