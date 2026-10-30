<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lampiran tiket (PDF di disk privat), hanya untuk petugas yang boleh melihat tiket.
 */
class DownloadTicketAttachmentController extends Controller
{
    public function __invoke(Ticket $ticket): StreamedResponse
    {
        Gate::authorize('view', $ticket);

        abort_unless($ticket->attachment_path && Storage::disk('local')->exists($ticket->attachment_path), 404);

        return Storage::disk('local')->download($ticket->attachment_path, "{$ticket->code}.pdf", ['Cache-Control' => 'no-store']);
    }
}
