<?php

namespace App\Services\Monitoring;

use App\Enums\MonitorStatus;

/**
 * Hasil mentah satu pengecekan (sebelum aturan batas gagal diterapkan).
 */
final readonly class CheckResult
{
    public function __construct(
        public MonitorStatus $status,
        public ?int $latencyMs = null,
        public ?float $packetLoss = null,
        public ?int $httpStatus = null,
        public ?string $error = null,
    ) {}

    public static function down(string $error, ?int $latencyMs = null, ?float $packetLoss = null, ?int $httpStatus = null): self
    {
        return new self(MonitorStatus::Down, $latencyMs, $packetLoss, $httpStatus, mb_substr($error, 0, 250));
    }

    /**
     * Online, atau Lambat bila latency melewati ambang / ada packet loss sebagian.
     */
    public static function reachable(int $latencyMs, int $warningMs, ?float $packetLoss = null, ?int $httpStatus = null): self
    {
        $slow = $latencyMs > $warningMs || ($packetLoss !== null && $packetLoss > 0);

        return new self(
            $slow ? MonitorStatus::Degraded : MonitorStatus::Up,
            $latencyMs,
            $packetLoss,
            $httpStatus,
            $slow ? ($packetLoss > 0 ? "Packet loss {$packetLoss}%" : "Lambat ({$latencyMs} ms)") : null,
        );
    }
}
