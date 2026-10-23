import type { ScheduleBlockState, WeekSchedule } from '@/types/public';

export const blockStyles: Record<ScheduleBlockState, { box: string; label: string }> = {
    done: { box: 'bg-nodata-bg text-nodata-text opacity-75', label: 'Selesai' },
    ongoing: { box: 'bg-online-bg text-online-text border border-teal', label: 'Berlangsung' },
    scheduled: { box: 'bg-available-bg text-available-text', label: 'Terjadwal' },
    maintenance: { box: 'bg-slow-bg text-slow-text', label: 'Maintenance' },
};

export function ScheduleLegend() {
    return (
        <ul className="flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted" aria-label="Keterangan warna">
            {(Object.keys(blockStyles) as ScheduleBlockState[]).map((state) => (
                <li key={state} className="flex items-center gap-1.5">
                    <span className={`size-3 rounded-[4px] ${blockStyles[state].box}`} aria-hidden="true" />
                    {blockStyles[state].label}
                </li>
            ))}
        </ul>
    );
}

/** Tabel lab × hari minggu berjalan; kolom hari ini disorot, bisa digeser horizontal di ponsel (SPEC §9.2 no. 5). */
export default function WeekScheduleTable({ week }: { week: WeekSchedule }) {
    return (
        // "relative" agar label sr-only (position: absolute) di dalam tabel ikut terpotong area scroll
        // dan tidak melebarkan halaman di ponsel.
        <div className="relative overflow-x-auto rounded-card border border-border bg-white">
            <table className="w-full min-w-[760px] border-collapse text-left text-sm">
                <caption className="sr-only">Jadwal pemakaian Lab CBT minggu ini</caption>
                <thead>
                    <tr className="border-b border-border">
                        <th scope="col" className="w-36 px-4 py-3 text-xs font-semibold tracking-wide text-muted-soft uppercase">
                            Lab
                        </th>
                        {week.days.map((day) => (
                            <th key={day.date} scope="col" className={`px-3 py-3 align-bottom ${day.isToday ? 'bg-faint' : ''}`}>
                                {day.isToday && <span className="eyebrow block text-[10px]">Hari ini</span>}
                                <span className="block font-semibold text-ink">{day.label}</span>
                                <span className="block font-mono text-xs font-normal text-muted-soft">{day.shortDate}</span>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {week.labs.map((lab) => (
                        <tr key={lab.id} className="border-b border-border last:border-b-0">
                            <th scope="row" className="px-4 py-3 align-top font-semibold text-ink">
                                <span className="flex items-center gap-2">
                                    <span className="size-2.5 shrink-0 rounded-full" style={{ backgroundColor: lab.color }} aria-hidden="true" />
                                    {lab.name}
                                </span>
                            </th>
                            {week.days.map((day) => {
                                const blocks = lab.blocks[day.date] ?? [];

                                return (
                                    <td key={day.date} className={`px-2 py-2 align-top ${day.isToday ? 'bg-faint/60' : ''}`}>
                                        {blocks.length === 0 ? (
                                            <span className="block px-1 py-1 text-xs text-muted-soft/60" aria-label="Kosong">
                                                –
                                            </span>
                                        ) : (
                                            <ul className="space-y-1.5">
                                                {blocks.map((block) => (
                                                    <li key={`${block.start}-${block.activityName}`} className={`rounded-control px-2 py-1.5 text-xs leading-snug ${blockStyles[block.state].box}`}>
                                                        <span className="block font-mono font-semibold">
                                                            {block.start}–{block.end}
                                                            {block.state === 'ongoing' && ' · sekarang'}
                                                        </span>
                                                        <span className="block">
                                                            {block.activityName}
                                                            {block.organizer && ` · ${block.organizer}`}
                                                        </span>
                                                        <span className="sr-only">({blockStyles[block.state].label})</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
