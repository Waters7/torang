<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Services\Helpdesk\TicketService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $result = app(TicketService::class)->create($data, auth()->user(), $data['attachment'] ?? null);

        // Tautan penilaian untuk pelapor hanya ditampilkan sekali di halaman detail.
        session()->flash('created_ticket_rating_url', route('tickets.rating', [$result['ticket']->code, $result['token']]));

        return $result['ticket'];
    }

    protected function getRedirectUrl(): string
    {
        return TicketResource::getUrl('view', ['record' => $this->record]);
    }
}
