import SectionHeading from '@/components/SectionHeading';
import StatusPill, { monitorStatusTone, type StatusTone } from '@/components/StatusPill';
import UptimeBars from '@/components/UptimeBars';
import PublicLayout from '@/layouts/PublicLayout';
import { formatPercent, formatTime } from '@/lib/time';
import type { DisplaySettings, PublicIncident, PublicSummary, StatusPageMonitor } from '@/types/public';
import { Head, usePoll } from '@inertiajs/react';

interface Props {
    summary: PublicSummary;
    status: { networks: StatusPageMonitor[]; apps: StatusPageMonitor[]; incidents: PublicIncident[] };
    display: DisplaySettings;
}

function overall(summary: PublicSummary): { tone: StatusTone; text: string } {
    const offline = summary.networks.offline + summary.apps.offline;
    const slow = summary.networks.slow + summary.apps.slow;

    if (offline > 0) {
        return { tone: 'offline', text: `${offline} layanan sedang mengalami gangguan` };
    }

    return slow > 0 ? { tone: 'slow', text: `${slow} layanan sedang lambat` } : { tone: 'online', text: 'Semua layanan berjalan normal' };
}

function MonitorList({ title, monitors, showBars }: { title: string; monitors: StatusPageMonitor[]; showBars: boolean }) {
    return (
        <div>
            <h3 className="text-lg font-semibold">{title}</h3>
            <ul className="mt-3 divide-y divide-border rounded-card border border-border bg-white">
                {monitors.map((monitor) => (
                    <li key={monitor.id} className="px-5 py-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="font-semibold text-ink">{monitor.name}</p>
                                {monitor.category && <p className="text-xs text-muted-soft">{monitor.category}</p>}
                            </div>
                            <StatusPill tone={monitorStatusTone[monitor.status.value]}>{monitor.status.label}</StatusPill>
                        </div>
                        {showBars && <UptimeBars days={monitor.bars} label={`Riwayat 90 hari ${monitor.name}`} className="mt-3" />}
                        <div className="mt-2 flex flex-wrap justify-between gap-2 font-mono text-xs text-muted-soft">
                            <span>90 hari lalu</span>
                            <span>
                                uptime 30 hari {formatPercent(monitor.uptime30d, 2)} · 90 hari {formatPercent(monitor.uptime90d, 2)}
                            </span>
                            <span>kemarin</span>
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function StatusPage({ summary, status, display }: Props) {
    usePoll(display.refreshSeconds * 1000, { only: ['summary', 'status'] });

    const state = overall(summary);

    return (
        <PublicLayout>
            <Head title="Status layanan" />

            <div className="mx-auto max-w-4xl px-4 py-10 sm:py-14">
                <p className="eyebrow">Status layanan</p>
                <h1 className="mt-3 text-[28px] leading-tight font-bold tracking-tight">Status jaringan & aplikasi kampus</h1>

                <div className="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-card border border-border bg-white p-5">
                    <StatusPill tone={state.tone} className="text-sm" wrap>
                        {state.text}
                    </StatusPill>
                    <p className="font-mono text-xs text-muted-soft">
                        Diperbarui {formatTime(summary.generatedAt)} WITA · cek tiap {summary.intervalMinutes} menit
                    </p>
                </div>

                <section aria-labelledby="judul-riwayat" className="mt-10 space-y-8">
                    <SectionHeading
                        id="judul-riwayat"
                        eyebrow="Riwayat 90 hari"
                        title="Uptime harian"
                        description={`Hijau ≥ ${formatPercent(display.goodPercent)}, kuning ≥ ${formatPercent(display.warningPercent, 0)}, merah di bawahnya, abu-abu tanpa data. Alamat IP dan URL internal tidak ditampilkan.`}
                    />
                    <MonitorList title="Jaringan per jurusan" monitors={status.networks} showBars={display.showHistoryBars} />
                    <MonitorList title="Aplikasi kampus" monitors={status.apps} showBars={display.showHistoryBars} />
                </section>

                <section aria-labelledby="judul-insiden" className="mt-12">
                    <SectionHeading id="judul-insiden" eyebrow="30 hari terakhir" title="Insiden" />
                    {status.incidents.length === 0 ? (
                        <p className="mt-4 rounded-card border border-border bg-white p-5 text-sm text-muted">Tidak ada insiden dalam 30 hari terakhir.</p>
                    ) : (
                        <ol className="mt-4 space-y-3">
                            {status.incidents.map((incident) => (
                                <li key={incident.id} className="rounded-card border border-border bg-white p-5">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <p className="font-semibold text-ink">
                                            {incident.monitor} <span className="font-normal text-muted-soft">· {incident.group}</span>
                                        </p>
                                        {incident.ongoing ? <StatusPill tone="offline">Masih berlangsung</StatusPill> : <StatusPill tone="online">Pulih</StatusPill>}
                                    </div>
                                    <p className="mt-2 font-mono text-xs text-muted">
                                        {incident.startedAt}
                                        {incident.resolvedAt ? ` – ${incident.resolvedAt}` : ''} WITA · {incident.duration}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            </div>
        </PublicLayout>
    );
}
