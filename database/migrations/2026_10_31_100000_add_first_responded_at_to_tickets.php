<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Waktu tanggap tiket untuk Dashboard Eksekutif: kapan petugas pertama kali menindaklanjuti.
 * Tiket lama diisi dari komentar petugas pertama, waktu selesai, atau (bila sudah ditindaklanjuti
 * tanpa keduanya) waktu perubahan terakhir sebagai perkiraan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('first_responded_at')->nullable()->after('assigned_to');
            $table->index(['category', 'created_at']);
        });

        $firstComments = DB::table('ticket_comments')
            ->selectRaw('ticket_id, MIN(created_at) as first_at')
            ->whereNotNull('user_id')
            ->groupBy('ticket_id')
            ->pluck('first_at', 'ticket_id');

        DB::table('tickets')->orderBy('id')->each(function (object $ticket) use ($firstComments): void {
            $candidates = array_filter([$firstComments[$ticket->id] ?? null, $ticket->resolved_at]);
            $respondedAt = $candidates !== [] ? min($candidates) : ($ticket->status !== 'open' ? $ticket->updated_at : null);

            // Data lama yang janggal (selesai sebelum diterima) tidak boleh menghasilkan waktu tanggap negatif.
            if ($respondedAt !== null && $respondedAt < $ticket->created_at) {
                $respondedAt = $ticket->created_at;
            }

            if ($respondedAt !== null) {
                DB::table('tickets')->where('id', $ticket->id)->update(['first_responded_at' => $respondedAt]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['category', 'created_at']);
            $table->dropColumn('first_responded_at');
        });
    }
};
