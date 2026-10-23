<?php

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Rules\MonitorHost;
use App\Services\Monitoring\Checkers\HttpChecker;
use App\Services\Monitoring\Checkers\IcmpChecker;
use App\Services\Monitoring\Checkers\TcpChecker;
use App\Services\Monitoring\MonitorConfig;
use App\Services\Monitoring\PingOutputParser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

function monitorConfig(int $warningMs = 150, array $codes = [200, 301, 302]): MonitorConfig
{
    return new MonitorConfig(60, 1, 3, 3, $warningMs, $codes);
}

describe('validasi host', function () {
    it('menerima alamat IP dan hostname', function (string $host) {
        expect(MonitorHost::isValid($host))->toBeTrue();
    })->with(['8.8.8.8', '203.0.113.10', '2001:db8::1', 'router.example.ac.id', 'localhost']);

    it('menolak input berbahaya atau tidak valid', function (string $host) {
        expect(MonitorHost::isValid($host))->toBeFalse();
    })->with([
        'perintah shell' => '8.8.8.8; rm -rf /',
        'substitusi' => '$(reboot)',
        'backtick' => '`id`',
        'pipe' => '8.8.8.8 | cat /etc/passwd',
        'opsi ping' => '-f 8.8.8.8',
        'spasi' => 'router example',
        'kosong' => '',
    ]);
});

describe('parser output ping', function () {
    it('membaca output Linux', function () {
        $output = "3 packets transmitted, 3 received, 0% packet loss, time 2003ms\nrtt min/avg/max/mdev = 11.204/12.587/14.006/1.150 ms";

        expect(PingOutputParser::parse($output, 3))->toBe(['loss' => 0.0, 'avg' => 12.587]);
    });

    it('membaca packet loss sebagian dan 100% di Linux', function () {
        expect(PingOutputParser::parse("3 packets transmitted, 2 received, 33.3333% packet loss\nrtt min/avg/max/mdev = 20.1/25.5/30.9/4.4 ms", 3))
            ->toBe(['loss' => 33.3333, 'avg' => 25.5])
            ->and(PingOutputParser::parse('3 packets transmitted, 0 received, 100% packet loss, time 2047ms', 3))
            ->toBe(['loss' => 100.0, 'avg' => null]);
    });

    it('membaca output Windows bahasa Inggris dan Indonesia', function () {
        $english = "Reply from 8.8.8.8: bytes=32 time=12ms TTL=117\nReply from 8.8.8.8: bytes=32 time=14ms TTL=117\nRequest timed out.\n    Packets: Sent = 3, Received = 2, Lost = 1 (33% loss),";
        $indonesian = "Balasan dari 127.0.0.1: byte=32 waktu<1ms TTL=128\nBalasan dari 127.0.0.1: byte=32 waktu=2ms TTL=128\n    Paket: Terkirim = 2, Diterima = 2, Hilang = 0 (0% hilang),";

        expect(PingOutputParser::parse($english, 3))->toBe(['loss' => 33.0, 'avg' => 13.0])
            ->and(PingOutputParser::parse($indonesian, 2))->toBe(['loss' => 0.0, 'avg' => 1.0]);
    });

    it('mengembalikan null untuk host yang tidak dikenal', function () {
        expect(PingOutputParser::parse('ping: tidak-ada.invalid: Name or service not known', 3))->toBe(['loss' => null, 'avg' => null]);
    });
});

describe('ICMP', function () {
    // Process palsu: menjalankan PHP yang mencetak contoh output ping.
    $fakePing = fn (string $output): IcmpChecker => new IcmpChecker(
        fn (array $command, float $timeout): Process => new Process([PHP_BINARY, '-r', 'echo '.var_export($output, true).';']),
        'Linux',
    );

    it('memakai argumen array dengan opsi Linux dan Windows', function () {
        expect((new IcmpChecker(osFamily: 'Linux'))->command('203.0.113.10', monitorConfig()))->toBe(['ping', '-c', '3', '-W', '1', '203.0.113.10'])
            ->and((new IcmpChecker(osFamily: 'Windows'))->command('203.0.113.10', monitorConfig()))->toBe(['ping', '-n', '3', '-w', '1000', '203.0.113.10']);
    });

    it('menentukan Online, Lambat, dan Offline dari hasil ping', function () use ($fakePing) {
        $monitor = Monitor::factory()->make(['host' => '203.0.113.10']);

        expect($fakePing("3 received, 0% packet loss\nrtt min/avg/max/mdev = 10/20/30/1 ms")->check($monitor, monitorConfig())->status)->toBe(MonitorStatus::Up)
            ->and($fakePing("3 received, 0% packet loss\nrtt min/avg/max/mdev = 200/210/220/1 ms")->check($monitor, monitorConfig())->status)->toBe(MonitorStatus::Degraded)
            ->and($fakePing("2 received, 33% packet loss\nrtt min/avg/max/mdev = 10/20/30/1 ms")->check($monitor, monitorConfig())->status)->toBe(MonitorStatus::Degraded)
            ->and($fakePing('0 received, 100% packet loss')->check($monitor, monitorConfig())->status)->toBe(MonitorStatus::Down);
    });

    it('tidak menjalankan ping untuk host berbahaya', function () {
        $called = false;
        $checker = new IcmpChecker(function () use (&$called) {
            $called = true;

            return new Process(['true']);
        });

        $result = $checker->check(Monitor::factory()->make(['host' => '8.8.8.8; rm -rf /']), monitorConfig());

        expect($result->status)->toBe(MonitorStatus::Down)
            ->and($called)->toBeFalse();
    });
});

describe('TCP', function () {
    it('Online bila port menerima koneksi dan Offline bila tidak', function () {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        $open = (new TcpChecker)->check(Monitor::factory()->tcp($port)->make(['host' => '127.0.0.1']), monitorConfig(5000));
        fclose($server);
        $closed = (new TcpChecker)->check(Monitor::factory()->tcp($port)->make(['host' => '127.0.0.1']), monitorConfig(5000));

        expect($open->status)->toBe(MonitorStatus::Up)
            ->and($open->latencyMs)->toBeInt()
            ->and($closed->status)->toBe(MonitorStatus::Down);
    });
});

describe('HTTP', function () {
    it('Online untuk kode normal dan mengirim user-agent TORANG', function () {
        Http::fake(['example.ac.id/*' => Http::response('<title>SIAKAD</title>', 200)]);

        $result = (new HttpChecker)->check(Monitor::factory()->application('https://example.ac.id/siakad')->make(['keyword' => 'SIAKAD']), monitorConfig(5000));

        expect($result->status)->toBe(MonitorStatus::Up)->and($result->httpStatus)->toBe(200);
        Http::assertSent(fn (Request $request) => str_starts_with($request->header('User-Agent')[0], 'TORANG-Monitor'));
    });

    it('menerima redirect 301 tanpa mengikutinya', function () {
        Http::fake(['example.ac.id/*' => Http::response('', 301, ['Location' => 'https://example.ac.id/login'])]);

        $result = (new HttpChecker)->check(Monitor::factory()->application('https://example.ac.id/siakad')->make(), monitorConfig(5000));

        expect($result->status)->toBe(MonitorStatus::Up)->and($result->httpStatus)->toBe(301);
        Http::assertSentCount(1);
    });

    it('Offline untuk kode di luar daftar normal, kata kunci hilang, atau gagal terhubung', function () {
        $monitor = Monitor::factory()->application('https://example.ac.id/pmb')->make(['keyword' => 'Pendaftaran']);

        Http::fake(['example.ac.id/*' => Http::sequence()
            ->push('Server Error', 500)
            ->push('Halaman maintenance', 200)
            ->pushFailedConnection('Connection timed out')]);

        $serverError = (new HttpChecker)->check($monitor, monitorConfig(5000));
        $missingKeyword = (new HttpChecker)->check($monitor, monitorConfig(5000));
        $unreachable = (new HttpChecker)->check($monitor, monitorConfig(5000));

        expect($serverError->status)->toBe(MonitorStatus::Down)
            ->and($serverError->error)->toContain('500')
            ->and($missingKeyword->status)->toBe(MonitorStatus::Down)
            ->and($missingKeyword->error)->toContain('Pendaftaran')
            ->and($unreachable->status)->toBe(MonitorStatus::Down)
            ->and($unreachable->error)->toContain('Tidak dapat terhubung');
    });
});
