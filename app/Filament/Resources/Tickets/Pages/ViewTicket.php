<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketActions;
use App\Filament\Resources\Tickets\TicketResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TicketActions::followUp(),
            TicketActions::addProgress(),
            TicketActions::contactReporter(),
            TicketActions::comment(),
            TicketActions::downloadAttachment(),
        ];
    }

    /**
     * Setelah tiket dibuat dari panel, tampilkan sekali tautan penilaian untuk diteruskan ke pelapor.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($url = session()->pull('created_ticket_rating_url')) {
            Notification::make()->info()->title('Tautan pribadi pelapor')->body($url)->persistent()->send();
        }
    }
}
