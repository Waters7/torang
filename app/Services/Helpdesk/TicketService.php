<?php

namespace App\Services\Helpdesk;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Alur tiket helpdesk: buat (publik, panel, atau dari log pemakaian), tindak lanjut sesuai alur status
 * (pelapor dikabari saat dikerjakan & selesai), komentar, dan penilaian pelapor dengan token rahasia
 * (hanya hash yang disimpan) atau tautan bertanda tangan dari pesan "selesai".
 */
class TicketService
{
    /**
     * Aturan isian laporan, sama untuk form publik dan panel.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'reporter_name' => ['required', 'string', 'max:255'],
            'reporter_contact' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ticket: Ticket, token: string} Token penilaian polos (hanya ditampilkan sekali).
     */
    public function create(array $input, ?User $actor = null, ?UploadedFile $attachment = null, ?UsageLog $usageLog = null): array
    {
        abort_if($usageLog && ! $actor, 403);

        if ($actor) {
            Gate::forUser($actor)->authorize($usageLog ? 'update' : 'create', $usageLog ?? Ticket::class);
        }

        $data = Validator::make($input, self::rules())->validate();
        Validator::make(['attachment' => $attachment], ['attachment' => ['nullable', 'file', 'mimes:pdf', 'max:2048']])->validate();

        $token = Str::random(64);
        $path = $attachment?->store('tickets', 'local');

        try {
            $ticket = DB::transaction(function () use ($data, $actor, $usageLog, $token, $path): Ticket {
                if ($usageLog) {
                    UsageLog::query()->whereKey($usageLog->id)->lockForUpdate()->firstOrFail();

                    if (Ticket::query()->where('usage_log_id', $usageLog->id)->exists()) {
                        throw ValidationException::withMessages(['usage_log' => 'Tiket untuk log ini sudah dibuat.']);
                    }
                }

                return Ticket::create([
                    ...$data,
                    'code' => 'TIK-'.now()->format('ym').'-'.strtoupper(bin2hex(random_bytes(6))),
                    'status' => TicketStatus::Open,
                    'priority' => TicketPriority::Normal,
                    'source' => $usageLog ? 'usage_log' : ($actor ? 'admin' : 'public'),
                    'created_by' => $actor?->id,
                    'usage_log_id' => $usageLog?->id,
                    'rating_token_hash' => hash('sha256', $token),
                    'attachment_path' => $path,
                ]);
            });
        } catch (Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $error;
        }

        return ['ticket' => $ticket, 'token' => $token];
    }

    /**
     * Ubah status/prioritas/petugas. Status hanya boleh berpindah sesuai TicketStatus::next();
     * "Dikerjakan" wajib punya petugas.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(Ticket $ticket, array $input, User $actor): Ticket
    {
        Gate::forUser($actor)->authorize('update', $ticket);

        $data = Validator::make($input, [
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('staff', 'id')->where('is_active', true)],
            'resolution_notes' => ['nullable', 'string', 'max:2000', 'required_if:status,'.TicketStatus::Resolved->value],
        ], [
            'resolution_notes.required_if' => 'Isi pekerjaan yang dilakukan sebelum menandai tiket selesai.',
        ])->validate();

        $previous = null;

        $updated = DB::transaction(function () use ($ticket, $data, &$previous): Ticket {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->status;
            $next = TicketStatus::from($data['status']);

            if ($next !== $locked->status && ! in_array($next, $locked->status->next(), true)) {
                throw ValidationException::withMessages(['status' => 'Perubahan status ini tidak diizinkan.']);
            }

            if ($next === TicketStatus::InProgress && empty($data['assigned_to'])) {
                throw ValidationException::withMessages(['assigned_to' => 'Pilih petugas sebelum mulai mengerjakan.']);
            }

            $locked->fill($data);

            // Tindak lanjut pertama petugas (status, prioritas, atau penugasan) = waktu tanggap.
            if ($locked->isDirty() && ! $locked->first_responded_at) {
                $locked->first_responded_at = now();
            }

            if ($next === TicketStatus::Resolved && ! $locked->resolved_at) {
                $locked->resolved_at = now();
            }

            if (! $next->isFinished()) {
                $locked->resolved_at = null;
            }

            if ($next === TicketStatus::Closed) {
                $locked->closed_at ??= now();
            }

            $locked->save();

            return $locked;
        });

        // Kabari pelapor saat mulai dikerjakan dan saat selesai (setelah transaksi tersimpan).
        if ($updated->status !== $previous && in_array($updated->status, [TicketStatus::InProgress, TicketStatus::Resolved], true)) {
            app(ReporterUpdates::class)->notifyStatusChange($updated);
        }

        return $updated;
    }

    public function comment(Ticket $ticket, string $body, bool $internal, User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $ticket);

        $data = Validator::make(['body' => $body], ['body' => ['required', 'string', 'max:5000']])->validate();

        $ticket->comments()->create([...$data, 'is_internal' => $internal, 'user_id' => $actor->id]);

        Ticket::query()->whereKey($ticket->id)->whereNull('first_responded_at')->update(['first_responded_at' => now()]);

        activity('tickets')->causedBy($actor)->performedOn($ticket)->log('Komentar tiket ditambahkan');
    }

    /**
     * Penilaian 1–5 oleh pelapor, sekali, setelah tiket selesai.
     *
     * @param  array<string, mixed>  $input
     */
    public function rate(Ticket $ticket, string $token, array $input): void
    {
        abort_unless($this->validToken($ticket, $token), 404);

        $this->rateVerified($ticket, $input);
    }

    /**
     * Penilaian lewat tautan yang sudah diverifikasi pemanggil (token rahasia atau URL bertanda tangan).
     *
     * @param  array<string, mixed>  $input
     */
    public function rateVerified(Ticket $ticket, array $input): void
    {
        $data = Validator::make($input, [
            'rating' => ['required', 'integer', 'between:1,5'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        DB::transaction(function () use ($ticket, $data): void {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isFinished() || $locked->rated_at) {
                throw ValidationException::withMessages(['rating' => 'Penilaian hanya dapat dikirim sekali setelah tiket selesai.']);
            }

            $locked->update([...$data, 'rated_at' => now()]);
        });
    }

    public function validToken(Ticket $ticket, string $token): bool
    {
        return strlen($token) === 64
            && filled($ticket->rating_token_hash)
            && hash_equals($ticket->rating_token_hash, hash('sha256', $token));
    }
}
