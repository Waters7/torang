<?php

namespace App\Services\Monitoring\Checkers;

use App\Models\Monitor;
use App\Services\Monitoring\CheckResult;
use App\Services\Monitoring\MonitorConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cek website/aplikasi: kode status, waktu respons, dan kata kunci wajib di halaman.
 * Redirect tidak diikuti supaya 301/302 bisa dinilai sesuai daftar kode normal.
 */
class HttpChecker implements MonitorChecker
{
    public const USER_AGENT = 'TORANG-Monitor/1.0 (+UPT TIK Poltekkes Kemenkes Manado)';

    public function check(Monitor $monitor, MonitorConfig $config): CheckResult
    {
        $url = (string) $monitor->url;

        if (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return CheckResult::down('URL tidak valid.');
        }

        $started = hrtime(true);

        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->timeout($config->timeoutSeconds)
                ->connectTimeout($config->timeoutSeconds)
                ->withoutRedirecting()
                ->get($url);
        } catch (ConnectionException $exception) {
            return CheckResult::down('Tidak dapat terhubung: '.Str::limit($exception->getMessage(), 180));
        } catch (Throwable $exception) {
            return CheckResult::down('Pengecekan gagal: '.Str::limit($exception->getMessage(), 180));
        }

        $latency = (int) round((hrtime(true) - $started) / 1_000_000);
        $status = $response->status();

        if (! in_array($status, $config->expectedStatusCodes, true)) {
            return CheckResult::down("Kode HTTP {$status} tidak termasuk kode normal.", $latency, httpStatus: $status);
        }

        if (filled($monitor->keyword) && ! str_contains($response->body(), $monitor->keyword)) {
            return CheckResult::down("Kata kunci \"{$monitor->keyword}\" tidak ditemukan di halaman.", $latency, httpStatus: $status);
        }

        return CheckResult::reachable($latency, $config->latencyWarningMs, httpStatus: $status);
    }
}
