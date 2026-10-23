import { formatLongDate, formatTime, useNow } from '@/lib/time';
import type { PublicSummary } from '@/types/public';
import { ChevronRightIcon } from '@heroicons/react/24/outline';

interface Row {
    href: string;
    label: string;
    value: string;
    detail: string;
    dot: string;
}

/** Kartu gelap ringkasan di hero: tanggal & jam WITA berjalan di klien + tiga baris status (SPEC §9.2 no. 3). */
export default function SummaryCard({ summary }: { summary: PublicSummary }) {
    const now = useNow(15_000);
    const { networks, apps, labs } = summary;

    const dot = (offline: number, slow: number) => (offline > 0 ? 'bg-offline' : slow > 0 ? 'bg-slow' : 'bg-online');

    const rows: Row[] = [
        {
            href: '#jaringan',
            label: 'Jaringan jurusan',
            value: `${networks.online} / ${networks.total} online`,
            detail: `${networks.slow} lambat · ${networks.offline} offline`,
            dot: dot(networks.offline, networks.slow),
        },
        {
            href: '#aplikasi',
            label: 'Aplikasi kampus',
            value: `${apps.online} / ${apps.total} normal`,
            detail: `${apps.slow} lambat · ${apps.offline} gangguan`,
            dot: dot(apps.offline, apps.slow),
        },
        {
            href: '#jadwal',
            label: 'Lab CBT saat ini',
            value: `${labs.inUse} / ${labs.total} dipakai`,
            detail: `${labs.available} tersedia · ${labs.maintenance} maintenance`,
            dot: labs.maintenance > 0 ? 'bg-slow' : labs.inUse > 0 ? 'bg-online' : 'bg-available',
        },
    ];

    return (
        <div className="rounded-card bg-ink-dark p-5 text-white sm:p-6">
            <div className="flex items-baseline justify-between gap-4 border-b border-white/10 pb-4">
                <p className="text-sm text-white/70">{formatLongDate(now)}</p>
                <p className="font-mono text-2xl font-semibold tracking-wider" aria-label={`Pukul ${formatTime(now)} WITA`}>
                    {formatTime(now)} <span className="text-xs font-medium text-teal">WITA</span>
                </p>
            </div>
            <ul className="divide-y divide-white/10">
                {rows.map((row) => (
                    <li key={row.href}>
                        <a href={row.href} className="group flex min-h-16 items-center gap-4 py-3.5">
                            <span className={`size-2.5 shrink-0 rounded-full ${row.dot}`} aria-hidden="true" />
                            <span className="flex-1">
                                <span className="block text-xs text-white/60">{row.label}</span>
                                <span className="mt-0.5 block font-mono text-lg font-semibold">{row.value}</span>
                                <span className="block text-xs text-white/60">{row.detail}</span>
                            </span>
                            <ChevronRightIcon className="size-5 text-white/40 transition group-hover:translate-x-0.5 group-hover:text-white" aria-hidden="true" />
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );
}
