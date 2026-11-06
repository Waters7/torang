<?php

namespace App\Services\Monitoring;

use App\Enums\MonitorGroup;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Models\Monitor;
use App\Services\PublicSite\PublicStatus;
use Illuminate\Support\Facades\DB;

class CampusApplicationRegistrar
{
    private const SAMPLES = [
        'Website Poltekkes' => 'https://example.ac.id/',
        'SIAKAD' => 'https://example.ac.id/siakad',
        'E-Learning' => 'https://example.ac.id/elearning',
        'PMB Online' => 'https://example.ac.id/pmb',
        'Repository' => 'https://example.ac.id/repository',
        'Email Kampus' => 'https://example.ac.id/mail',
        'Server Ujian CBT' => 'https://example.ac.id/cbt',
    ];

    /** @return array{created: int, updated: int, unchanged: int, paused: int, ids: list<int>} */
    public function sync(bool $pauseSamples = false): array
    {
        $result = DB::transaction(function () use ($pauseSamples): array {
            $existing = Monitor::query()->where('group', MonitorGroup::Application)
                ->where('type', MonitorType::Http)->orderBy('id')->lockForUpdate()->get();
            $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'paused' => 0, 'ids' => []];

            foreach (config('campus-applications') as $index => $application) {
                $key = $this->urlKey($application['url']);
                $monitor = $existing->first(fn (Monitor $item): bool => $this->urlKey((string) $item->url) === $key);

                if (! $monitor) {
                    $monitor = Monitor::create([
                        ...$application,
                        'group' => MonitorGroup::Application,
                        'type' => MonitorType::Http,
                        'check_ssl' => str_starts_with($application['url'], 'https://'),
                        'is_active' => true,
                        'is_public' => true,
                        'sort_order' => $index + 1,
                        'current_status' => MonitorStatus::Unknown,
                    ]);
                    $existing->push($monitor);
                    $counts['created']++;
                } else {
                    // Pertahankan pilihan privasi, jeda, maintenance, PIC, interval, dan riwayat operator.
                    $monitor->fill($application);
                    if ($monitor->isDirty()) {
                        $monitor->save();
                        $counts['updated']++;
                    } else {
                        $counts['unchanged']++;
                    }
                }

                $counts['ids'][] = $monitor->id;
            }

            if ($pauseSamples) {
                foreach ($existing as $monitor) {
                    if ((self::SAMPLES[$monitor->name] ?? null) === $monitor->url && ($monitor->is_active || $monitor->is_public)) {
                        $monitor->update(['is_active' => false, 'is_public' => false]);
                        $counts['paused']++;
                    }
                }
            }

            return $counts;
        });

        PublicStatus::flushCache();

        return $result;
    }

    private function urlKey(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['host'])) {
            return $url;
        }

        $port = isset($parts['port']) && ! in_array($parts['port'], [80, 443], true) ? ':'.$parts['port'] : '';

        return strtolower($parts['host']).$port.rtrim($parts['path'] ?? '', '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
