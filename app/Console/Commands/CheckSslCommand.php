<?php

namespace App\Console\Commands;

use App\Actions\Monitoring\CheckSslCertificates;
use Illuminate\Console\Command;

class CheckSslCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'monitors:check-ssl';

    /**
     * @var string
     */
    protected $description = 'Perbarui masa berlaku sertifikat SSL aplikasi dan kirim peringatan';

    public function handle(CheckSslCertificates $action): int
    {
        $this->info($action->handle().' peringatan SSL terkirim.');

        return self::SUCCESS;
    }
}
