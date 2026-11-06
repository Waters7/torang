<?php

namespace App\Services\Helpdesk;

use App\Enums\TicketProgressType;
use App\Models\Ticket;
use App\Models\TicketProgressEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TicketProgressService
{
    /** @param array<string, mixed> $input */
    public function add(Ticket $ticket, array $input, User $actor): TicketProgressEntry
    {
        Gate::forUser($actor)->authorize('update', $ticket);

        $data = Validator::make($input, [
            'type' => ['required', Rule::enum(TicketProgressType::class)],
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'required', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($ticket, $data, $actor): TicketProgressEntry {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $entry = $locked->progressEntries()->create([
                'type' => $data['type'],
                'body' => trim($data['body']),
                'is_internal' => $data['is_internal'] ?? false,
                'status' => $locked->status,
                'user_id' => $actor->id,
            ]);

            if (! $locked->first_responded_at) {
                $locked->update(['first_responded_at' => now()]);
            }

            activity('tickets')->causedBy($actor)->performedOn($locked)
                ->event('progress_added')
                ->withProperties(['entry_id' => $entry->id, 'type' => $entry->type->value, 'is_internal' => $entry->is_internal])
                ->log('Catatan penanganan tiket ditambahkan');

            return $entry;
        });
    }
}
