<?php

namespace App\Notifications\Monitoring;

use App\Models\Monitor;

class SslCertificateExpiring extends MonitorNotification
{
    public function __construct(Monitor $monitor, public int $daysLeft)
    {
        parent::__construct($monitor);
    }

    public function title(): string
    {
        return "Sertifikat SSL {$this->monitor->name} berakhir {$this->daysLeft} hari lagi";
    }

    public function lines(): array
    {
        return [
            ...$this->identityLines(),
            'Berlaku sampai: '.$this->monitor->ssl_expires_at?->translatedFormat('j F Y'),
            'Segera perpanjang sertifikat agar aplikasi tetap bisa diakses tanpa peringatan keamanan.',
        ];
    }
}
