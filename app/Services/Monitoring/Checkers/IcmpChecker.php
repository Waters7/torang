<?php

namespace App\Services\Monitoring\Checkers;

use App\Models\Monitor;
use App\Rules\MonitorHost;
use App\Services\Monitoring\CheckResult;
use App\Services\Monitoring\MonitorConfig;
use App\Services\Monitoring\PingOutputParser;
use Closure;
use Symfony\Component\Process\Process;

/**
 * Ping ICMP lewat Symfony Process dengan argumen array (tidak pernah string shell).
 */
class IcmpChecker implements MonitorChecker
{
    /**
     * @param  (Closure(list<string>, float): Process)|null  $processFactory  Untuk test; default membuat Process biasa.
     */
    public function __construct(
        private readonly ?Closure $processFactory = null,
        private readonly string $osFamily = PHP_OS_FAMILY,
    ) {}

    public function check(Monitor $monitor, MonitorConfig $config): CheckResult
    {
        $host = (string) $monitor->host;

        if (! MonitorHost::isValid($host)) {
            return CheckResult::down('Host tidak valid.');
        }

        $command = $this->command($host, $config);
        $timeout = $config->packetCount * $config->timeoutSeconds + 5;
        $process = $this->processFactory ? ($this->processFactory)($command, $timeout) : new Process($command, timeout: $timeout);

        $process->run();

        ['loss' => $loss, 'avg' => $avg] = PingOutputParser::parse($process->getOutput().$process->getErrorOutput(), $config->packetCount);

        if ($loss === null) {
            return CheckResult::down('Ping gagal: '.(trim($process->getErrorOutput()) ?: 'host tidak dapat dihubungi'));
        }

        if ($loss >= 100 || $avg === null) {
            return CheckResult::down('Tidak ada balasan ping (loss 100%).', packetLoss: 100.0);
        }

        return CheckResult::reachable((int) round($avg), $config->latencyWarningMs, $loss);
    }

    /**
     * @return list<string>
     */
    public function command(string $host, MonitorConfig $config): array
    {
        if ($this->osFamily === 'Windows') {
            return ['ping', '-n', (string) $config->packetCount, '-w', (string) ($config->timeoutSeconds * 1000), $host];
        }

        return ['ping', '-c', (string) $config->packetCount, '-W', (string) $config->timeoutSeconds, $host];
    }
}
