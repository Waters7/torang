import StatusPill from '@/components/StatusPill';
import type { LabNow } from '@/types/public';
import { ArrowRightIcon, ComputerDesktopIcon, UserGroupIcon } from '@heroicons/react/24/outline';
import { Link } from '@inertiajs/react';

/** Kartu kondisi lab saat ini: berlangsung, tersedia, atau maintenance (SPEC §9.2 no. 4). */
export default function LabNowCard({ lab, now }: { lab: LabNow; now: Date }) {
    const { current } = lab;
    const ongoing = lab.state === 'ongoing' && current;

    let progress = 0;
    let remaining = 0;

    if (ongoing) {
        const start = new Date(current.startsAt).getTime();
        const end = new Date(current.endsAt).getTime();
        progress = Math.min(1, Math.max(0, (now.getTime() - start) / (end - start)));
        remaining = Math.max(0, Math.ceil((end - now.getTime()) / 60_000));
    }

    return (
        <article className={`flex h-full flex-col rounded-card bg-white p-5 ${ongoing ? 'border-2 border-teal' : 'border border-border'}`}>
            <header className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 className="flex items-center gap-2 font-semibold text-ink">
                        <span className="size-2.5 shrink-0 rounded-full" style={{ backgroundColor: lab.color }} aria-hidden="true" />
                        {lab.name}
                    </h3>
                    {lab.location && <p className="mt-0.5 text-xs text-muted-soft">{lab.location}</p>}
                </div>
                {lab.state === 'ongoing' && <StatusPill tone="online">Berlangsung</StatusPill>}
                {lab.state === 'available' && <StatusPill tone="available">Tersedia</StatusPill>}
                {lab.state === 'maintenance' && <StatusPill tone="slow">Maintenance</StatusPill>}
            </header>

            {ongoing && (
                <div className="mt-4 flex flex-1 flex-col">
                    <p className="font-semibold text-ink">{current.activityName}</p>
                    <p className="text-sm text-muted">{[current.organizer, current.activityType].filter(Boolean).join(' · ')}</p>

                    <div className="mt-4">
                        <div className="h-2 overflow-hidden rounded-full bg-surface" role="progressbar" aria-valuenow={Math.round(progress * 100)} aria-valuemin={0} aria-valuemax={100} aria-label="Progres kegiatan">
                            <div className="h-full rounded-full bg-teal transition-all" style={{ width: `${progress * 100}%` }} />
                        </div>
                        <div className="mt-1.5 flex justify-between font-mono text-xs text-muted">
                            <span>{current.start}</span>
                            <span className="font-semibold text-teal-text">{remaining > 0 ? `sisa ${remaining} menit` : 'selesai'}</span>
                            <span>{current.end}</span>
                        </div>
                    </div>

                    <dl className="mt-4 space-y-1.5 text-sm text-muted">
                        <div className="flex items-center gap-2">
                            <ComputerDesktopIcon className="size-4 shrink-0" aria-hidden="true" />
                            <dt className="sr-only">Perangkat</dt>
                            <dd>
                                <span className="font-mono">
                                    {current.participants}/{lab.readyPcs}
                                </span>{' '}
                                PC dipakai
                            </dd>
                        </div>
                        <div className="flex items-start gap-2">
                            <UserGroupIcon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            <dt className="sr-only">Petugas jaga</dt>
                            <dd>{lab.staff.length > 0 ? `Petugas jaga: ${lab.staff.join(', ')}` : 'Petugas jaga belum dijadwalkan'}</dd>
                        </div>
                    </dl>
                </div>
            )}

            {lab.state === 'available' && (
                <div className="mt-4 flex flex-1 flex-col">
                    <p className="font-semibold text-ink">{lab.next ? `Kosong hingga ${lab.next.start}` : 'Tidak ada kegiatan lagi hari ini'}</p>
                    {lab.next && (
                        <p className="mt-1 text-sm text-muted">
                            Berikutnya: <span className="font-mono">{lab.next.start}–{lab.next.end}</span> {lab.next.activityName}
                        </p>
                    )}
                    <p className="mt-1 text-sm text-muted">
                        <span className="font-mono">{lab.readyPcs}</span> PC siap pakai
                    </p>
                    <Link href={`/ajukan?lab=${encodeURIComponent(lab.code)}`} className="mt-auto inline-flex min-h-11 items-center gap-1.5 pt-3 text-sm font-semibold text-teal-text hover:text-ink">
                        Ajukan di lab ini
                        <ArrowRightIcon className="size-4" aria-hidden="true" />
                    </Link>
                </div>
            )}

            {lab.state === 'maintenance' && (
                <div className="mt-4">
                    <p className="font-semibold text-ink">{lab.maintenanceNote}</p>
                    <p className="mt-1 text-sm text-muted">{lab.maintenanceUntil ? `Perkiraan selesai ${lab.maintenanceUntil} WITA` : 'Perkiraan selesai belum ditentukan'}</p>
                </div>
            )}
        </article>
    );
}
