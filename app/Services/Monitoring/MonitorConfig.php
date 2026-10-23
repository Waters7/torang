<?php

namespace App\Services\Monitoring;

use App\Enums\MonitorGroup;
use App\Models\Monitor;
use App\Services\SettingsService;

/**
 * Pengaturan efektif sebuah monitor: nilai di monitor bila diisi, selain itu pengaturan global (SPEC §5.1–5.2).
 */
final readonly class MonitorConfig
{
    public const DEFAULT_STATUS_CODES = [200, 301, 302];

    /**
     * @param  list<int>  $expectedStatusCodes
     */
    public function __construct(
        public int $intervalSeconds,
        public int $timeoutSeconds,
        public int $packetCount,
        public int $failureThreshold,
        public int $latencyWarningMs,
        public array $expectedStatusCodes,
    ) {}

    public static function for(Monitor $monitor, SettingsService $settings): self
    {
        $globalWarning = $monitor->group === MonitorGroup::Application
            ? (int) $settings->get('monitoring.response_warning_ms', 2000)
            : (int) $settings->get('monitoring.latency_warning_ms', 150);

        return new self(
            intervalSeconds: $monitor->interval_seconds ?? (int) $settings->get('monitoring.interval_minutes', 1) * 60,
            timeoutSeconds: max(1, $monitor->timeout_seconds ?? (int) $settings->get('monitoring.timeout_seconds', 5)),
            packetCount: max(1, $monitor->packet_count ?? (int) $settings->get('monitoring.packet_count', 3)),
            failureThreshold: max(1, $monitor->failure_threshold ?? (int) $settings->get('monitoring.failure_threshold', 3)),
            latencyWarningMs: $monitor->latency_warning_ms ?? $globalWarning,
            expectedStatusCodes: array_map('intval', $monitor->expected_status_codes ?: self::DEFAULT_STATUS_CODES),
        );
    }
}
