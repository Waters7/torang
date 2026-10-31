import StatusPill from '@/components/StatusPill';
import TorangMark from '@/components/TorangMark';
import { formatTime, useNow } from '@/lib/time';
import type { LabTvBoard, LabTvSession } from '@/types/public';
import { CheckCircleIcon, ClockIcon, ComputerDesktopIcon, UserGroupIcon, WrenchScrewdriverIcon } from '@heroicons/react/24/outline';
import { Head, usePage, usePoll } from '@inertiajs/react';

/** Warna palet Kemenkes, urutan seperti modul simbol TORANG. */
const PALETTE = ['#1BBBCB', '#04B6A3', '#D4E022', '#42B97C'];

/** Batas baris per daftar agar layar TV tidak perlu digulir. */
const MAX_ROWS = 4;

function SessionRow({ session, done = false }: { session: LabTvSession; done?: boolean }) {
    return (
        <li className={`flex items-start gap-4 rounded-2xl border border-white/10 px-4 py-3 2xl:px-6 2xl:py-4 ${done ? 'opacity-60' : 'bg-white/5'}`}>
            <span className="shrink-0 font-mono text-lg font-semibold text-cyan 2xl:text-2xl">
                {session.start}–{session.end}
            </span>
            <span className="min-w-0">
                <span className="block truncate text-lg font-semibold 2xl:text-2xl">{session.activityName}</span>
                <span className="block truncate text-sm text-white/60 2xl:text-lg">{[session.organizer, session.activityType].filter(Boolean).join(' · ')}</span>
            </span>
            {done && <CheckCircleIcon className="ml-auto size-6 shrink-0 text-green 2xl:size-8" aria-label="Selesai" />}
        </li>
    );
}

function SessionList({ title, sessions, empty, done = false }: { title: string; sessions: LabTvSession[]; empty: string; done?: boolean }) {
    const shown = done ? sessions.slice(-MAX_ROWS) : sessions.slice(0, MAX_ROWS);
    const hidden = sessions.length - shown.length;

    return (
        <section aria-label={title}>
            <h2 className="mb-3 font-mono text-xs font-semibold tracking-[0.14em] text-teal uppercase 2xl:text-base">{title}</h2>
            {sessions.length === 0 ? (
                <p className="rounded-2xl border border-dashed border-white/15 px-4 py-4 text-white/60 2xl:text-xl">{empty}</p>
            ) : (
                <ul className="grid gap-3">
                    {shown.map((session) => (
                        <SessionRow key={session.startsAt} session={session} done={done} />
                    ))}
                    {hidden > 0 && <li className="px-2 text-sm text-white/60 2xl:text-lg">+{hidden} sesi lainnya</li>}
                </ul>
            )}
        </section>
    );
}

/** Layar TV di depan lab: kegiatan berlangsung, sesi berikutnya, dan yang sudah selesai hari ini. */
export default function LabTv({ board }: { board: LabTvBoard }) {
    const { site } = usePage().props;
    const now = useNow(1_000);
    const { lab, current } = board;

    usePoll(board.refreshSeconds * 1000, { only: ['board'] });

    let progress = 0;
    let remaining = 0;

    if (current) {
        const start = new Date(current.startsAt).getTime();
        const end = new Date(current.endsAt).getTime();
        progress = Math.min(1, Math.max(0, (now.getTime() - start) / (end - start)));
        remaining = Math.max(0, Math.ceil((end - now.getTime()) / 60_000));
    }

    const ongoing = board.state === 'ongoing' && current;
    const nextToday = board.upcoming[0] ?? null;

    return (
        <div className="flex min-h-screen flex-col bg-ink-dark text-white">
            <Head title={`TV ${lab.name}`} />

            <header className="flex flex-wrap items-center justify-between gap-6 border-b border-white/10 px-6 py-5 lg:px-12 lg:py-7">
                <div className="flex min-w-0 items-center gap-4 2xl:gap-6">
                    <TorangMark faint={false} onDark className="size-12 shrink-0 lg:size-16 2xl:size-20" />
                    <div className="min-w-0">
                        <p className="font-mono text-xs font-semibold tracking-[0.14em] text-teal uppercase 2xl:text-base">TORANG · Layar Lab CBT</p>
                        <h1 className="flex items-center gap-3 truncate text-3xl font-bold lg:text-5xl 2xl:text-6xl">
                            <span className="size-3 shrink-0 rounded-full lg:size-4" style={{ backgroundColor: lab.color }} aria-hidden="true" />
                            {lab.name}
                        </h1>
                        {lab.location && <p className="mt-1 text-white/60 2xl:text-xl">{lab.location}</p>}
                    </div>
                </div>
                <div className="text-right">
                    <p className="font-mono text-4xl font-semibold lg:text-6xl 2xl:text-7xl" aria-live="off">
                        {formatTime(now)} <span className="text-lg text-white/60 lg:text-2xl">WITA</span>
                    </p>
                    <p className="mt-1 text-white/60 2xl:text-xl">{board.dateLabel}</p>
                </div>
            </header>

            <main className="grid flex-1 gap-6 px-6 py-6 lg:grid-cols-[1.6fr_1fr] lg:gap-10 lg:px-12 lg:py-10">
                <section
                    aria-label="Kondisi lab saat ini"
                    className={`flex flex-col rounded-3xl bg-white/5 p-6 lg:p-10 ${ongoing ? 'border-2 border-teal' : 'border border-white/10'}`}
                >
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="font-mono text-xs font-semibold tracking-[0.14em] text-teal uppercase 2xl:text-base">Sedang berlangsung</h2>
                        {board.state === 'ongoing' && <StatusPill tone="online">Berlangsung</StatusPill>}
                        {board.state === 'available' && <StatusPill tone="available">Tersedia</StatusPill>}
                        {board.state === 'maintenance' && <StatusPill tone="slow">Maintenance</StatusPill>}
                    </div>

                    {ongoing && (
                        <div className="mt-6 flex flex-1 flex-col">
                            <p className="text-4xl leading-tight font-bold lg:text-6xl 2xl:text-7xl">{current.activityName}</p>
                            <p className="mt-3 text-lg text-white/70 lg:text-2xl 2xl:text-3xl">
                                {[current.organizer, current.activityType].filter(Boolean).join(' · ')}
                            </p>

                            <div className="mt-auto pt-10">
                                <div
                                    className="h-4 overflow-hidden rounded-full bg-white/10 2xl:h-6"
                                    role="progressbar"
                                    aria-valuenow={Math.round(progress * 100)}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-label="Progres kegiatan"
                                >
                                    <div className="h-full rounded-full bg-teal transition-all" style={{ width: `${progress * 100}%` }} />
                                </div>
                                <div className="mt-3 flex items-center justify-between font-mono text-xl lg:text-3xl 2xl:text-4xl">
                                    <span>{current.start}</span>
                                    <span className="font-semibold text-lime">{remaining > 0 ? `sisa ${remaining} menit` : 'selesai'}</span>
                                    <span>{current.end}</span>
                                </div>
                            </div>

                            <dl className="mt-8 grid gap-4 text-lg text-white/80 sm:grid-cols-2 lg:text-xl 2xl:text-2xl">
                                <div className="flex items-center gap-3">
                                    <ComputerDesktopIcon className="size-7 shrink-0 text-cyan" aria-hidden="true" />
                                    <dt className="sr-only">Peserta</dt>
                                    <dd>
                                        <span className="font-mono font-semibold text-white">
                                            {current.participants}/{lab.readyPcs}
                                        </span>{' '}
                                        PC dipakai
                                    </dd>
                                </div>
                                <div className="flex items-start gap-3">
                                    <UserGroupIcon className="size-7 shrink-0 text-cyan" aria-hidden="true" />
                                    <dt className="sr-only">Petugas jaga</dt>
                                    <dd>{board.staff.length > 0 ? `Petugas: ${board.staff.join(', ')}` : 'Petugas jaga belum dijadwalkan'}</dd>
                                </div>
                            </dl>
                        </div>
                    )}

                    {board.state === 'available' && (
                        <div className="mt-6 flex flex-1 flex-col justify-center">
                            <p className="text-4xl font-bold lg:text-6xl 2xl:text-7xl">Lab tersedia</p>
                            <p className="mt-4 flex items-center gap-3 text-xl text-white/70 lg:text-3xl 2xl:text-4xl">
                                <ClockIcon className="size-8 shrink-0 text-cyan" aria-hidden="true" />
                                {nextToday ? `Kosong hingga ${nextToday.start} WITA` : 'Tidak ada kegiatan lagi hari ini'}
                            </p>
                            <p className="mt-6 text-lg text-white/60 lg:text-2xl">
                                <span className="font-mono text-white">{lab.readyPcs}</span> PC siap pakai
                            </p>
                        </div>
                    )}

                    {board.state === 'maintenance' && (
                        <div className="mt-6 flex flex-1 flex-col justify-center">
                            <WrenchScrewdriverIcon className="size-14 text-slow 2xl:size-20" aria-hidden="true" />
                            <p className="mt-4 text-4xl font-bold lg:text-6xl 2xl:text-7xl">{board.maintenanceNote}</p>
                            {current && (
                                <p className="mt-4 font-mono text-xl text-white/70 lg:text-3xl">
                                    {current.start}–{current.end} WITA
                                </p>
                            )}
                        </div>
                    )}
                </section>

                <div className="grid content-start gap-8">
                    <SessionList title="Sesi berikutnya" sessions={board.upcoming} empty="Tidak ada sesi lagi hari ini." />
                    {board.upcoming.length === 0 && board.nextDay && (
                        <p className="-mt-4 rounded-2xl bg-white/5 px-4 py-3 text-white/70 2xl:text-xl">
                            Berikutnya: {board.nextDay.dateLabel} ·{' '}
                            <span className="font-mono text-white">
                                {board.nextDay.start}–{board.nextDay.end}
                            </span>{' '}
                            {board.nextDay.activityName}
                        </p>
                    )}
                    <SessionList title="Sudah selesai hari ini" sessions={board.completed} empty="Belum ada kegiatan yang selesai hari ini." done />
                </div>
            </main>

            <footer className="flex flex-wrap items-center justify-between gap-4 px-6 pb-6 text-sm text-white/60 lg:px-12 2xl:text-lg">
                <div className="flex items-center gap-4">
                    <div className="grid w-28 grid-cols-4 gap-1" aria-hidden="true">
                        {PALETTE.map((color) => (
                            <span key={color} className="h-1.5 rounded-full" style={{ backgroundColor: color }} />
                        ))}
                    </div>
                    <span>Diperbarui otomatis tiap {board.refreshSeconds} detik</span>
                </div>
                <span>
                    {site.unit} · {site.institution}
                </span>
            </footer>
        </div>
    );
}
