@props(['tickets'])

<section class="mt-6" aria-labelledby="tv-ticket-title">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="tv-ticket-title" class="text-sm font-semibold tracking-[0.14em] text-[#70DBCD] uppercase 2xl:text-base">Tiket belum selesai</h2>
            <p class="mt-1 text-xs text-white/60 2xl:text-sm">Baru, dikerjakan, dan menunggu · prioritas mendesak lebih dulu</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <span class="rounded-full bg-white/10 px-3 py-1.5">{{ $tickets['total'] }} tiket</span>
            @if ($tickets['unassigned'] > 0)
                <span class="rounded-full bg-[#E9A23B]/15 px-3 py-1.5 text-[#F5C977]">{{ $tickets['unassigned'] }} belum ditugaskan</span>
            @endif
        </div>
    </div>

    @if ($tickets['items'])
        <div class="max-h-[28rem] overflow-y-auto rounded-2xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#70DBCD]"
            tabindex="0" role="region" aria-label="Daftar tiket belum selesai, gulir untuk melihat seluruh tiket">
            <ul class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($tickets['items'] as $ticket)
                    <li wire:key="tv-ticket-{{ $ticket['id'] }}" class="min-w-0 rounded-2xl border border-white/10 bg-white/5 p-4 2xl:p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs text-white/60 [font-family:var(--mono-font-family)] 2xl:text-sm">{{ $ticket['code'] }}</span>
                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-[#D64545]/20 text-[#FF9C9C]' => $ticket['status'] === 'open',
                                'bg-[#1BBBCB]/15 text-[#70DBCD]' => $ticket['status'] === 'in_progress',
                                'bg-[#E9A23B]/15 text-[#F5C977]' => $ticket['status'] === 'waiting',
                            ])>{{ $ticket['statusLabel'] }}</span>
                        </div>
                        <h3 class="mt-3 text-base leading-snug font-semibold wrap-anywhere 2xl:text-lg">{{ $ticket['title'] }}</h3>
                        <p class="mt-2 text-xs text-white/60 2xl:text-sm">
                            @if ($ticket['department']){{ $ticket['department'] }} · @endif
                            <span @class([
                                'font-semibold',
                                'text-[#FF9C9C]' => $ticket['priority'] === 'urgent',
                                'text-[#F5C977]' => $ticket['priority'] === 'high',
                            ])>Prioritas {{ $ticket['priorityLabel'] }}</span>
                        </p>
                        <div class="mt-4 flex items-start gap-2 border-t border-white/10 pt-3">
                            <x-heroicon-o-user-circle class="mt-0.5 size-5 shrink-0 text-[#70DBCD]" aria-hidden="true" />
                            <div class="min-w-0">
                                <p class="text-xs text-white/50">Ditugaskan kepada</p>
                                <p class="mt-0.5 text-sm font-semibold wrap-anywhere 2xl:text-base {{ $ticket['assignee'] ? 'text-white' : 'text-[#F5C977]' }}">{{ $ticket['assignee'] ?? 'Belum ditugaskan' }}</p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-white/50">Masuk {{ $ticket['receivedAt'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <p class="rounded-2xl border border-dashed border-white/15 px-5 py-6 text-sm text-white/60">Tidak ada tiket yang belum selesai.</p>
    @endif
</section>
