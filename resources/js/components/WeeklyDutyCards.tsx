import SectionHeading from '@/components/SectionHeading';
import StatusPill from '@/components/StatusPill';
import type { PublicDutyWeek } from '@/types/public';
import { ClockIcon, UserGroupIcon } from '@heroicons/react/24/outline';

export default function WeeklyDutyCards({ week }: { week: PublicDutyWeek }) {
    return (
        <section id="petugas" aria-labelledby="judul-petugas" className="scroll-mt-6">
            <SectionHeading
                id="judul-petugas"
                eyebrow="Tim yang mendampingi Anda"
                title="Petugas minggu ini"
                description={week.label}
                aside={
                    week.published ? (
                        <StatusPill tone="online">{week.staffCount} petugas terjadwal</StatusPill>
                    ) : (
                        <StatusPill tone="nodata">Belum diterbitkan</StatusPill>
                    )
                }
            />
            {week.published ? (
                <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {week.days.map((day) => (
                        <article
                            key={day.date}
                            className={`min-w-0 rounded-card border bg-white ${day.isToday ? 'border-teal' : 'border-border'}`}
                        >
                            <header
                                className={`flex items-center justify-between gap-2 rounded-t-card border-b border-border px-5 py-4 ${day.isToday ? 'bg-faint' : 'bg-white'}`}
                            >
                                <div className="flex items-baseline gap-2">
                                    <h3 className="font-semibold">{day.label}</h3>
                                    <span className="font-mono text-xs text-muted-soft">{day.shortDate}</span>
                                </div>
                                {day.isToday && <span className="text-xs font-semibold text-teal-text">Hari ini</span>}
                            </header>
                            {day.slots.length ? (
                                <ul className="divide-y divide-border px-5">
                                    {day.slots.map((slot, index) => (
                                        <li key={`${slot.shift}-${slot.coverage}-${index}`} className="py-4">
                                            <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
                                                <span className="font-semibold">{slot.shift}</span>
                                                <span className="inline-flex items-center gap-1 font-mono">
                                                    <ClockIcon className="size-3.5" aria-hidden="true" />
                                                    {slot.start}–{slot.end} WITA
                                                </span>
                                            </p>
                                            <ul className="mt-2 space-y-1">
                                                {slot.staff.map((name) => (
                                                    <li
                                                        key={name}
                                                        className="flex items-start gap-2 text-sm font-semibold [overflow-wrap:anywhere]"
                                                    >
                                                        <span
                                                            aria-hidden="true"
                                                            className="mt-1.5 size-1.5 shrink-0 rounded-full bg-teal"
                                                        />
                                                        {name}
                                                    </li>
                                                ))}
                                            </ul>
                                            <p className="mt-2 text-xs text-muted-soft">{slot.coverage}</p>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="px-5 py-6 text-sm text-muted-soft">Belum ada penugasan.</p>
                            )}
                        </article>
                    ))}
                </div>
            ) : (
                <div className="mt-5 flex flex-col gap-4 rounded-card border border-dashed border-border bg-white p-6 sm:flex-row sm:items-center">
                    <span className="flex size-12 shrink-0 items-center justify-center rounded-control bg-faint text-teal-text">
                        <UserGroupIcon className="size-6" aria-hidden="true" />
                    </span>
                    <div>
                        <h3 className="font-semibold">Jadwal petugas minggu ini belum tersedia</h3>
                        <p className="mt-1 text-sm leading-6 text-muted">
                            Nama petugas, jam shift, dan cakupan lab akan tampil setelah jadwal diterbitkan oleh UPT TIK.
                        </p>
                    </div>
                </div>
            )}
        </section>
    );
}
