<?php

namespace App\Services\Monitoring;

use App\Actions\Monitoring\RecordCheckResult;
use App\Enums\MonitorType;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Services\Monitoring\Checkers\HttpChecker;
use App\Services\Monitoring\Checkers\IcmpChecker;
use App\Services\Monitoring\Checkers\MonitorChecker;
use App\Services\Monitoring\Checkers\TcpChecker;
use App\Services\SettingsService;
use Throwable;

/**
 * Jalankan satu pengecekan monitor dan catat hasilnya.
 */
class MonitorRunner
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly RecordCheckResult $recorder,
    ) {}

    public function run(Monitor $monitor): MonitorCheck
    {
        $config = MonitorConfig::for($monitor, $this->settings);

        try {
            $result = $this->checker($monitor->type)->check($monitor, $config);
        } catch (Throwable $exception) {
            report($exception);
            $result = CheckResult::down('Kesalahan saat mengecek: '.$exception->getMessage());
        }

        return $this->recorder->handle($monitor, $result, $config);
    }

    public function checker(MonitorType $type): MonitorChecker
    {
        return app(match ($type) {
            MonitorType::Icmp => IcmpChecker::class,
            MonitorType::Tcp => TcpChecker::class,
            MonitorType::Http => HttpChecker::class,
        });
    }
}
