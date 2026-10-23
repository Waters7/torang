import { formatPercent } from '@/lib/time';
import type { DayBar, HourBar } from '@/types/public';

const hourColors: Record<string, string> = {
    up: 'bg-online',
    degraded: 'bg-slow',
    down: 'bg-offline',
    unknown: 'bg-nodata',
    paused: 'bg-nodata',
};

const hourLabels: Record<string, string> = {
    up: 'Online',
    degraded: 'Lambat',
    down: 'Offline',
    unknown: 'Tanpa data',
    paused: 'Tanpa data',
};

const dayColors: Record<string, string> = {
    good: 'bg-online',
    warning: 'bg-slow',
    bad: 'bg-offline',
};

interface UptimeBarsProps {
    hours?: HourBar[];
    days?: DayBar[];
    label: string;
    className?: string;
}

/**
 * Bar riwayat status: per jam (24 jam) atau per hari (30/90 hari). Abu-abu = tanpa data.
 * Ringkasan teks disediakan untuk pembaca layar.
 */
export default function UptimeBars({ hours, days, label, className = '' }: UptimeBarsProps) {
    const items = hours
        ? hours.map((bar) => ({ key: bar.label, color: bar.status ? hourColors[bar.status] : 'bg-nodata', title: `${bar.label} · ${bar.status ? hourLabels[bar.status] : 'Tanpa data'}` }))
        : (days ?? []).map((bar) => ({
              key: bar.label,
              color: bar.level ? dayColors[bar.level] : 'bg-nodata',
              title: `${bar.label} · ${bar.uptime === null ? 'Tanpa data' : `uptime ${formatPercent(bar.uptime, 2)}`}`,
          }));

    const problems = items.filter((item) => item.color === 'bg-offline').length;
    const slow = items.filter((item) => item.color === 'bg-slow').length;

    return (
        <div className={className}>
            {/* Celah 1px di ponsel supaya 90 bar tetap muat di lebar 320px. */}
            <div className="flex h-7 items-stretch gap-px sm:gap-[2px]" aria-hidden="true">
                {items.map((item) => (
                    <span key={item.key} title={item.title} className={`min-w-px flex-1 rounded-[1px] sm:rounded-[2px] ${item.color}`} />
                ))}
            </div>
            <p className="sr-only">
                {label}: {items.length} periode, {problems} bermasalah, {slow} lambat.
            </p>
        </div>
    );
}
