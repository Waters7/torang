<?php

namespace App\Console\Commands;

use App\Actions\Bookings\CompleteFinishedBookings;
use Illuminate\Console\Command;

class MarkCompletedBookings extends Command
{
    /**
     * @var string
     */
    protected $signature = 'bookings:mark-completed';

    /**
     * @var string
     */
    protected $description = 'Tandai selesai pengajuan disetujui yang jam selesainya sudah lewat';

    public function handle(CompleteFinishedBookings $action): int
    {
        $count = $action->handle();

        $this->info("{$count} pengajuan ditandai selesai.");

        return self::SUCCESS;
    }
}
