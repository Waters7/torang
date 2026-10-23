<?php

namespace App\Services\Monitoring\Checkers;

use App\Models\Monitor;
use App\Rules\MonitorHost;
use Carbon\CarbonImmutable;

/**
 * Membaca tanggal kedaluwarsa sertifikat SSL sebuah aplikasi (dijalankan harian).
 * Verifikasi rantai sertifikat dimatikan karena yang dibutuhkan hanya tanggal berlakunya.
 */
class SslChecker
{
    public function expiresAt(Monitor $monitor, int $timeoutSeconds = 10): ?CarbonImmutable
    {
        $parts = parse_url((string) $monitor->url);
        $host = $parts['host'] ?? null;

        if (($parts['scheme'] ?? null) !== 'https' || ! MonitorHost::isValid($host)) {
            return null;
        }

        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $client = @stream_socket_client("ssl://{$host}:".($parts['port'] ?? 443), $errno, $errstr, $timeoutSeconds, STREAM_CLIENT_CONNECT, $context);

        if ($client === false) {
            return null;
        }

        $certificate = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
        fclose($client);

        $info = $certificate ? openssl_x509_parse($certificate) : false;

        return $info && isset($info['validTo_time_t']) ? CarbonImmutable::createFromTimestamp($info['validTo_time_t'], config('app.timezone')) : null;
    }
}
