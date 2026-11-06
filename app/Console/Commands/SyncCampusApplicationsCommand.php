<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Services\Monitoring\CampusApplicationRegistrar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncCampusApplicationsCommand extends Command
{
    protected $signature = 'monitors:sync-campus-applications {--pause-samples : Jeda tujuh monitor aplikasi contoh bawaan, tanpa menghapus riwayatnya}';

    protected $description = 'Daftarkan aplikasi kampus yang disetujui, tanpa membuat duplikat URL';

    public function handle(CampusApplicationRegistrar $registrar): int
    {
        $result = Cache::lock('monitoring:sync-campus-applications', 60)->block(5,
            fn (): array => $registrar->sync((bool) $this->option('pause-samples')));

        $this->info("Aplikasi: {$result['created']} baru, {$result['updated']} diperbarui, {$result['unchanged']} sudah sesuai; {$result['paused']} contoh dijeda.");
        $this->table(['Aplikasi', 'Kategori', 'URL', 'Status'], Monitor::query()->whereIn('id', $result['ids'])->ordered()->get()
            ->map(fn (Monitor $monitor): array => [$monitor->name, $monitor->category, $monitor->url, $monitor->current_status->label()])->all());

        return self::SUCCESS;
    }
}
