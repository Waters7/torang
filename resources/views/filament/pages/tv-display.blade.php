@php
    /**
     * Mode TV / NOC: satu layar gelap yang terbaca dari jauh. Warna status selalu disertai label (SPEC §11.1).
     */
    $tone = fn (string $status): array => match ($status) {
        'up' => ['dot' => 'bg-[#42B97C]', 'text' => 'text-[#7BE0A9]'],
        'degraded', 'maintenance' => ['dot' => 'bg-[#E9A23B]', 'text' => 'text-[#F5C977]'],
        'down' => ['dot' => 'bg-[#D64545] animate-pulse', 'text' => 'text-[#FF9C9C]'],
        default => ['dot' => 'bg-[#C9D6D4]/50', 'text' => 'text-white/50'],
    };
    $problems = fn (array $counts): int => $counts['offline'] + $counts['slow'];
@endphp

<div
    wire:poll.60s
    class="min-h-dvh bg-[#0B1F1D] px-6 py-5 text-white antialiased lg:px-10 lg:py-7"
    x-data
    x-ref="screen"
>
    {{-- Kepala: identitas, jam WITA, tombol layar penuh --}}
    <header class="flex flex-wrap items-center justify-between gap-6 border-b border-white/10 pb-5">
        <div class="flex items-center gap-4">
            <x-torang-mark :dark="true" class="size-11" />
            <div>
                <p class="text-xl font-bold tracking-[0.18em]">TORANG</p>
                <p class="text-xs tracking-[0.2em] text-[#70DBCD] uppercase [font-family:var(--mono-font-family)]">Pusat operasi IT · {{ $unit }} · {{ $institution }}</p>
            </div>
        </div>

        <div class="flex items-center gap-6">
            <div wire:ignore class="text-right" x-data="{
                now: new Date(),
                init() { setInterval(() => (this.now = new Date()), 1000) },
                part(options) { return this.now.toLocaleString('id-ID', { timeZone: 'Asia/Makassar', ...options }) },
            }">
                <p class="text-4xl leading-none font-semibold tabular-nums [font-family:var(--mono-font-family)] lg:text-5xl">
                    <span x-text="part({ hour: '2-digit', minute: '2-digit' }).replace('.', ':')">{{ $now->format('H:i') }}</span><span class="text-2xl text-white/40 lg:text-3xl" x-text="':' + part({ second: '2-digit' }).padStart(2, '0')"></span>
                    <span class="ml-1 text-base font-normal text-white/50">WITA</span>
                </p>
                <p class="mt-2 text-sm text-white/60" x-text="part({ weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })">{{ $now->locale('id')->translatedFormat('l, j F Y') }}</p>
            </div>
            <div class="flex flex-col gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-white/15 px-3 py-2 text-xs text-white/70 hover:border-[#70DBCD] hover:text-white"
                    x-on:click="document.fullscreenElement ? document.exitFullscreen() : $refs.screen.requestFullscreen()"
                >
                    Layar penuh
                </button>
                <a href="{{ $dashboardUrl }}" class="rounded-lg border border-white/15 px-3 py-2 text-center text-xs text-white/70 hover:border-[#70DBCD] hover:text-white">
                    Dashboard
                </a>
            </div>
        </div>
    </header>

    {{-- Pengumuman aktif --}}
    @foreach ($announcements as $announcement)
        <div @class([
            'mt-4 flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm',
            'bg-[#D64545]/20 text-[#FFC2C2]' => $announcement['type'] === 'incident',
            'bg-[#E9A23B]/15 text-[#F5C977]' => $announcement['type'] === 'maintenance',
            'bg-white/5 text-white/80' => $announcement['type'] === 'info',
        ])>
            <span class="text-[11px] font-semibold tracking-[0.16em] uppercase">{{ $announcement['label'] }}</span>
            <span class="truncate">{{ $announcement['title'] }}</span>
        </div>
    @endforeach

    {{-- Angka utama --}}
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan">
        @if ($showMonitors)
            @foreach (['network' => 'Jaringan online', 'app' => 'Aplikasi online'] as $key => $label)
                @php($count = $counts[$key])
                <div @class([
                    'rounded-2xl border p-5',
                    'border-[#D64545]/50 bg-[#D64545]/10' => $count['offline'] > 0,
                    'border-white/10 bg-white/[0.04]' => $count['offline'] === 0,
                ])>
                    <p class="text-sm text-white/60">{{ $label }}</p>
                    <p class="mt-2 text-5xl font-semibold tabular-nums [font-family:var(--mono-font-family)]">
                        {{ $count['online'] }}<span class="text-2xl text-white/40"> / {{ $count['total'] }}</span>
                    </p>
                    <p class="mt-3 text-sm {{ $problems($count) > 0 ? 'text-[#F5C977]' : 'text-white/50' }}">
                        @if ($count['offline'] + $count['slow'] + $count['maintenance'] === 0)
                            Semua normal
                        @else
                            {{ collect([
                                $count['offline'] ? $count['offline'].' offline' : null,
                                $count['slow'] ? $count['slow'].' lambat' : null,
                                $count['maintenance'] ? $count['maintenance'].' maintenance' : null,
                            ])->filter()->implode(' · ') }}
                        @endif
                    </p>
                </div>
            @endforeach
        @endif

        @if ($labs)
            <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
                <p class="text-sm text-white/60">Lab CBT dipakai</p>
                <p class="mt-2 text-5xl font-semibold tabular-nums [font-family:var(--mono-font-family)]">
                    {{ $labs['inUse'] }}<span class="text-2xl text-white/40"> / {{ $labs['total'] }}</span>
                </p>
                <p class="mt-3 text-sm text-white/50">
                    {{ count($bookings) }} kegiatan hari ini{{ $labs['maintenance'] ? ' · '.$labs['maintenance'].' maintenance' : '' }}
                </p>
            </div>
        @endif

        @if ($openIncidents !== null)
            <div @class([
                'rounded-2xl border p-5',
                'border-[#D64545]/50 bg-[#D64545]/10' => $openIncidents > 0,
                'border-white/10 bg-white/[0.04]' => $openIncidents === 0,
            ])>
                <p class="text-sm text-white/60">Insiden aktif</p>
                <p class="mt-2 text-5xl font-semibold tabular-nums [font-family:var(--mono-font-family)] {{ $openIncidents > 0 ? 'text-[#FF9C9C]' : '' }}">{{ $openIncidents }}</p>
                <p class="mt-3 text-sm text-white/50">{{ $openIncidents > 0 ? 'Sedang ditangani' : 'Tidak ada gangguan' }}</p>
            </div>
        @endif
    </section>

    {{-- Status jaringan & aplikasi: bermasalah di urutan teratas --}}
    @if ($showMonitors)
        <section class="mt-6 grid gap-6 xl:grid-cols-12">
            <div class="xl:col-span-7">
                <h2 class="mb-3 text-xs font-semibold tracking-[0.18em] text-[#70DBCD] uppercase">Jaringan per jurusan</h2>
                <div class="grid grid-cols-2 gap-2.5 md:grid-cols-3 2xl:grid-cols-4">
                    @forelse ($networks as $item)
                        @php($t = $tone($item['status']))
                        <div class="flex min-w-0 items-center gap-3 rounded-xl bg-white/[0.04] px-3.5 py-3">
                            <span class="size-2.5 shrink-0 rounded-full {{ $t['dot'] }}" aria-hidden="true"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[15px] font-medium">{{ $item['name'] }}</p>
                                <p class="truncate text-xs {{ $t['text'] }}">{{ $item['label'] }}{{ $item['detail'] ? ' · '.$item['detail'] : '' }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-white/50">Belum ada monitor jaringan.</p>
                    @endforelse
                </div>
            </div>

            <div class="xl:col-span-5">
                <h2 class="mb-3 text-xs font-semibold tracking-[0.18em] text-[#70DBCD] uppercase">Aplikasi kampus</h2>
                <ul class="divide-y divide-white/5 overflow-hidden rounded-xl bg-white/[0.04]">
                    @forelse ($apps as $item)
                        @php($t = $tone($item['status']))
                        <li class="flex items-center gap-3 px-4 py-3">
                            <span class="size-2.5 shrink-0 rounded-full {{ $t['dot'] }}" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1 truncate text-[15px] font-medium">{{ $item['name'] }}</span>
                            <span class="shrink-0 text-xs {{ $t['text'] }}">{{ $item['label'] }}{{ $item['detail'] ? ' · '.$item['detail'] : '' }}</span>
                        </li>
                    @empty
                        <li class="px-4 py-3 text-sm text-white/50">Belum ada monitor aplikasi.</li>
                    @endforelse
                </ul>
            </div>
        </section>
    @endif

    {{-- Kegiatan CBT & petugas hari ini --}}
    @if ($showCbt)
        <section class="mt-6 grid gap-6 xl:grid-cols-12">
            <div class="xl:col-span-8">
                <h2 class="mb-3 text-xs font-semibold tracking-[0.18em] text-[#70DBCD] uppercase">Kegiatan CBT hari ini</h2>
                <ul class="space-y-2">
                    @forelse ($bookings as $booking)
                        <li @class([
                            'rounded-xl px-4 py-3',
                            'bg-[#04B6A3]/15 ring-1 ring-[#04B6A3]/60' => $booking['state'] === 'ongoing',
                            'bg-white/[0.04]' => $booking['state'] === 'upcoming',
                            'bg-white/[0.02] opacity-50' => $booking['state'] === 'done',
                        ])>
                            <div class="flex items-center gap-4">
                                <span class="w-28 shrink-0 text-sm tabular-nums [font-family:var(--mono-font-family)]">{{ $booking['time'] }}</span>
                                <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $booking['color'] }}" aria-hidden="true"></span>
                                <span class="w-24 shrink-0 truncate text-sm text-white/70">{{ $booking['lab'] }}</span>
                                <span class="min-w-0 flex-1 truncate text-[15px] font-medium">
                                    {{ $booking['activity'] }}@if ($booking['organizer'])<span class="text-white/50"> · {{ $booking['organizer'] }}</span>@endif
                                </span>
                                <span class="shrink-0 text-xs text-white/60">
                                    {{ match ($booking['state']) { 'ongoing' => 'Berlangsung', 'done' => 'Selesai', default => 'Terjadwal' } }}
                                </span>
                            </div>
                            @if ($booking['progress'] !== null)
                                <div class="mt-2 h-1 overflow-hidden rounded-full bg-white/10" role="progressbar" aria-valuenow="{{ $booking['progress'] }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="h-full rounded-full bg-[#04B6A3]" style="width: {{ $booking['progress'] }}%"></div>
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="rounded-xl bg-white/[0.04] px-4 py-3 text-sm text-white/50">Tidak ada kegiatan hari ini.</li>
                    @endforelse
                </ul>
            </div>

            <div class="xl:col-span-4">
                <h2 class="mb-3 text-xs font-semibold tracking-[0.18em] text-[#70DBCD] uppercase">Petugas jaga hari ini</h2>
                <ul class="space-y-2">
                    @forelse ($duty as $person)
                        <li class="flex items-center gap-3 rounded-xl bg-white/[0.04] px-4 py-3">
                            <span @class(['size-2.5 shrink-0 rounded-full', 'bg-[#42B97C]' => $person['now'], 'bg-white/20' => ! $person['now']]) aria-hidden="true"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[15px] font-medium">{{ $person['name'] }}</p>
                                <p class="truncate text-xs text-white/50">{{ $person['shift'] }} · {{ $person['coverage'] }}</p>
                            </div>
                            @if ($person['now'])
                                <span class="shrink-0 text-xs text-[#7BE0A9]">Bertugas</span>
                            @endif
                        </li>
                    @empty
                        <li class="rounded-xl bg-white/[0.04] px-4 py-3 text-sm text-white/50">Jadwal petugas hari ini belum diterbitkan.</li>
                    @endforelse
                </ul>
            </div>
        </section>
    @endif

    <footer class="mt-6 flex flex-wrap justify-between gap-2 border-t border-white/10 pt-4 text-xs text-white/40">
        <span>Data diperbarui {{ $now->format('H:i') }} WITA · otomatis tiap menit</span>
        <span>TORANG · Tata Operasional Ruang CBT, Aplikasi, Network &amp; Gangguan</span>
    </footer>
</div>
