<?php

namespace App\Models;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Tiket helpdesk (SPEC §8 no. 1) dari form publik, panel, atau log pemakaian Lab CBT.
 * Penilaian pelapor memakai token rahasia yang hanya disimpan dalam bentuk hash.
 */
#[Fillable([
    'code', 'reporter_name', 'reporter_contact', 'department_id', 'usage_log_id', 'category', 'title', 'description',
    'priority', 'status', 'source', 'assigned_to', 'first_responded_at', 'created_by', 'resolved_at', 'resolution_notes', 'closed_at', 'rating_token_hash',
    'rating', 'feedback', 'rated_at', 'attachment_path',
])]
#[Hidden(['rating_token_hash'])]
class Ticket extends Model
{
    use LogsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'rated_at' => 'datetime',
            'rating' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tickets')
            ->logOnly(['code', 'status', 'priority', 'assigned_to', 'resolution_notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Petugas yang menangani.
     *
     * @return BelongsTo<Staff, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<UsageLog, $this>
     */
    public function usageLog(): BelongsTo
    {
        return $this->belongsTo(UsageLog::class);
    }

    /**
     * @return HasMany<TicketComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * Nomor WhatsApp pelapor (format 62…) bila kontaknya berupa nomor HP Indonesia.
     */
    public function reporterPhone(): ?string
    {
        $digits = preg_replace('/[\s().\-+]/', '', $this->reporter_contact ?? '') ?? '';

        return preg_match('/^(0|62)8\d{7,12}$/', $digits) ? WhatsAppChannel::normalizePhone($digits) : null;
    }

    /**
     * Email pelapor bila kontaknya berupa alamat email.
     */
    public function reporterEmail(): ?string
    {
        $contact = trim($this->reporter_contact ?? '');

        return filter_var($contact, FILTER_VALIDATE_EMAIL) ? $contact : null;
    }

    /**
     * Menit dari tiket diterima sampai tindak lanjut pertama petugas (tidak pernah negatif).
     */
    public function responseMinutes(): ?int
    {
        return $this->first_responded_at ? max(0, (int) $this->created_at->diffInMinutes($this->first_responded_at)) : null;
    }

    /**
     * Menit dari tiket diterima sampai ditandai selesai (tidak pernah negatif).
     */
    public function resolutionMinutes(): ?int
    {
        return $this->resolved_at ? max(0, (int) $this->created_at->diffInMinutes($this->resolved_at)) : null;
    }
}
