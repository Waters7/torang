<?php

namespace App\Services\Monitoring\Checkers;

use App\Models\Monitor;
use App\Rules\MonitorHost;
use App\Services\Monitoring\CheckResult;
use App\Services\Monitoring\MonitorConfig;

/**
 * Cek port TCP: berhasil tidaknya koneksi dan lama waktu koneksinya.
 */
class TcpChecker implements MonitorChecker
{
    public function check(Monitor $monitor, MonitorConfig $config): CheckResult
    {
        $host = (string) $monitor->host;
        $port = (int) $monitor->port;

        if (! MonitorHost::isValid($host) || $port < 1 || $port > 65535) {
            return CheckResult::down('Host atau port tidak valid.');
        }

        $address = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? "[{$host}]" : $host;
        $started = hrtime(true);
        $socket = @stream_socket_client("tcp://{$address}:{$port}", $errno, $errstr, $config->timeoutSeconds);
        $latency = (int) round((hrtime(true) - $started) / 1_000_000);

        if ($socket === false) {
            return CheckResult::down("Port {$port} tidak dapat dihubungi: ".($errstr ?: 'timeout'), $latency);
        }

        fclose($socket);

        return CheckResult::reachable($latency, $config->latencyWarningMs);
    }
}
