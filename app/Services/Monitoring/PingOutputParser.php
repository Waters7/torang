<?php

namespace App\Services\Monitoring;

/**
 * Membaca packet loss (%) dan rata-rata RTT (ms) dari output ping Linux (iputils) maupun
 * Windows (bahasa Inggris/Indonesia, untuk development).
 */
final class PingOutputParser
{
    /**
     * @return array{loss: ?float, avg: ?float} loss null = output tidak bisa dibaca (mis. host tidak dikenal)
     */
    public static function parse(string $output, int $count): array
    {
        // Linux: "3 packets transmitted, 3 received, 0% packet loss" & "rtt min/avg/max/mdev = 1.1/2.2/3.3/0.4 ms"
        if (preg_match('/([\d.]+)% packet loss/i', $output, $loss)) {
            $avg = preg_match('/=\s*[\d.]+\/([\d.]+)\/[\d.]+/', $output, $rtt) ? (float) $rtt[1] : null;

            return ['loss' => (float) $loss[1], 'avg' => $avg];
        }

        // Windows: "Reply from …: bytes=32 time=12ms TTL=57" / "Balasan dari …: byte=32 waktu=12ms TTL=57"
        preg_match_all('/(?:time|waktu)\s*([=<])\s*(\d+)\s*ms/i', $output, $replies, PREG_SET_ORDER);

        if ($replies !== [] || preg_match('/\((\d+)%\s*(?:loss|hilang|kehilangan)\)/i', $output)) {
            $times = array_map(fn (array $reply): int => $reply[1] === '<' ? 0 : (int) $reply[2], $replies);
            $lost = preg_match('/\((\d+)%\s*(?:loss|hilang|kehilangan)\)/i', $output, $windowsLoss)
                ? (float) $windowsLoss[1]
                : round(max(0, $count - count($times)) / max(1, $count) * 100, 2);

            return ['loss' => $lost, 'avg' => $times === [] ? null : round(array_sum($times) / count($times), 2)];
        }

        return ['loss' => null, 'avg' => null];
    }
}
