import StatusPill, { monitorStatusTone } from '@/components/StatusPill';
import UptimeBars from '@/components/UptimeBars';
import { formatPercent } from '@/lib/time';
import type { AppStatus, NetworkStatus } from '@/types/public';

function networkMetric(monitor: NetworkStatus): string {
    if (monitor.status.value === 'down') {
        return monitor.downSince ? `down sejak ${monitor.downSince}` : 'tidak terjangkau';
    }

    if (monitor.packetLoss && monitor.packetLoss > 0) {
        return `loss ${formatPercent(monitor.packetLoss, 0)}`;
    }

    return monitor.latencyMs === null ? '–' : `${monitor.latencyMs} ms`;
}

/** Kartu status jaringan satu jurusan: pill, bar 24 jam, latency, uptime 30 hari (SPEC §9.2 no. 6). */
export function NetworkCard({ monitor, showBars }: { monitor: NetworkStatus; showBars: boolean }) {
    return (
        <article className="rounded-card border border-border bg-white p-5">
            <header className="flex items-start justify-between gap-3">
                <h3 className="font-semibold text-ink">{monitor.name}</h3>
                <StatusPill tone={monitorStatusTone[monitor.status.value]}>{monitor.status.label}</StatusPill>
            </header>
            {showBars && <UptimeBars hours={monitor.bars} label={`Riwayat 24 jam ${monitor.name}`} className="mt-4" />}
            <div className="mt-3 flex items-baseline justify-between gap-2 font-mono text-xs text-muted">
                <span className={monitor.status.value === 'down' ? 'font-semibold text-offline-text' : ''}>{networkMetric(monitor)}</span>
                <span>uptime 30 hari {formatPercent(monitor.uptime30d)}</span>
            </div>
        </article>
    );
}

/** Baris status satu aplikasi: nama + kategori, bar 30 hari, waktu respons, uptime, pill (SPEC §9.2 no. 7). */
export function AppStatusRow({ monitor, showBars }: { monitor: AppStatus; showBars: boolean }) {
    return (
        <li className="grid gap-3 px-5 py-4 sm:grid-cols-[minmax(0,1.2fr)_minmax(0,2fr)_auto] sm:items-center sm:gap-6">
            <div className="flex items-start justify-between gap-3 sm:block">
                <div>
                    <p className="font-semibold text-ink">{monitor.name}</p>
                    {monitor.category && <p className="text-xs text-muted-soft">{monitor.category}</p>}
                </div>
                <StatusPill tone={monitorStatusTone[monitor.status.value]} className="sm:hidden">
                    {monitor.status.label}
                </StatusPill>
            </div>
            {showBars ? <UptimeBars days={monitor.bars} label={`Riwayat 30 hari ${monitor.name}`} /> : <span className="hidden sm:block" />}
            <div className="flex items-center justify-between gap-4 sm:justify-end">
                <div className="font-mono text-xs text-muted sm:text-right">
                    <span className="block">{monitor.status.value === 'down' || monitor.latencyMs === null ? '–' : `${monitor.latencyMs} ms`}</span>
                    <span className="block">uptime {formatPercent(monitor.uptime30d)}</span>
                </div>
                <StatusPill tone={monitorStatusTone[monitor.status.value]} className="hidden min-w-24 justify-center sm:inline-flex">
                    {monitor.status.label}
                </StatusPill>
            </div>
        </li>
    );
}
