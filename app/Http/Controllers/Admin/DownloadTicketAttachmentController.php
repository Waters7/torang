<?php

namespace App\Http\Controllers\Admin;

use App\Models\Ticket;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DownloadTicketAttachmentController
{
    public function __invoke(Ticket $ticket)
    {
        Gate::authorize('view', $ticket);
        abort_unless($ticket->attachment_path && Storage::disk('local')->exists($ticket->attachment_path), 404);

        return Storage::disk('local')->download($ticket->attachment_path, $ticket->code.'.pdf', ['Cache-Control' => 'no-store']);
    }
}
