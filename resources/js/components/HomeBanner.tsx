import {
    ArrowRightIcon,
    CalendarDaysIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ComputerDesktopIcon,
    MegaphoneIcon,
    PauseIcon,
    PlayIcon,
    SignalIcon,
} from '@heroicons/react/24/outline';
import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { PublicAnnouncement, PublicSummary } from '@/types/public';

interface Slide {
    id: string;
    tag: string;
    title: string;
    description: string;
    href: string;
    action: string;
    kind: 'lab' | 'status' | 'help' | 'announcement';
}

export default function HomeBanner({ announcements, summary }: { announcements: PublicAnnouncement[]; summary: PublicSummary }) {
    const slides: Slide[] = [
        ...announcements.map(
            (item): Slide => ({
                id: `announcement-${item.id}`,
                tag: item.label,
                title: item.title,
                description: item.body,
                href: '#pengumuman',
                action: 'Baca pengumuman',
                kind: 'announcement',
            }),
        ),
        {
            id: 'lab',
            tag: 'Lab CBT Center',
            title: 'Rencanakan kegiatan CBT Anda.',
            description: 'Cek ketersediaan ruang, pilih jadwal, lalu ajukan pemakaian lab. Semua dalam satu tempat.',
            href: '/ajukan',
            action: 'Ajukan pemakaian lab',
            kind: 'lab',
        },
        // Slide status hanya bila jaringan/aplikasi ditampilkan di halaman depan (diatur dari dashboard).
        ...(summary.networks.total + summary.apps.total > 0
            ? [
                  {
                      id: 'status',
                      tag: 'Jaringan & aplikasi',
                      title: 'Cek layanan sebelum mulai aktivitas.',
                      description: 'Lihat kondisi jaringan jurusan dan aplikasi kampus, lengkap dengan riwayat ketersediaannya.',
                      href: '/status',
                      action: 'Lihat status layanan',
                      kind: 'status',
                  } satisfies Slide,
              ]
            : []),
        {
            id: 'help',
            tag: 'Helpdesk UPT TIK',
            title: 'Ada kendala IT? Laporkan di sini.',
            description: 'Sampaikan gangguan yang Anda alami dan pantau penanganannya menggunakan kode tiket.',
            href: '/lapor',
            action: 'Buat laporan gangguan',
            kind: 'help',
        },
    ];
    const [activeId, setActiveId] = useState(slides[0].id);
    const [paused, setPaused] = useState(false);
    const [hovered, setHovered] = useState(false);
    const [reducedMotion, setReducedMotion] = useState(true);
    const pointerPaused = useRef<boolean | null>(null);
    const index = Math.max(
        0,
        slides.findIndex((slide) => slide.id === activeId),
    );
    const slide = slides[index];
    const slideIds = slides.map((item) => item.id).join(',');

    useEffect(() => {
        const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
        const update = () => setReducedMotion(preference.matches);
        update();
        preference.addEventListener('change', update);
        return () => preference.removeEventListener('change', update);
    }, []);

    useEffect(() => {
        if (paused || hovered || reducedMotion) return;
        const ids = slideIds.split(',');
        const timer = window.setInterval(() => {
            if (!document.hidden) setActiveId((current) => ids[(Math.max(0, ids.indexOf(current)) + 1) % ids.length]);
        }, 8000);
        return () => window.clearInterval(timer);
    }, [paused, hovered, reducedMotion, slideIds]);

    function select(next: number) {
        setPaused(true);
        setActiveId(slides[(next + slides.length) % slides.length].id);
    }

    return (
        <section
            aria-label="Informasi dan layanan TORANG"
            aria-roledescription="karusel"
            className="neo-banner relative isolate overflow-hidden rounded-[24px] bg-ink text-white"
            onMouseEnter={() => setHovered(true)}
            onMouseLeave={() => setHovered(false)}
            onFocusCapture={() => setPaused(true)}
            onKeyDown={(event) => {
                if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                    event.preventDefault();
                    select(index + (event.key === 'ArrowRight' ? 1 : -1));
                }
            }}
        >
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -top-40 -right-24 size-[500px] rounded-full border-[70px] border-white/[0.025]"
            />
            <div
                key={slide.id}
                id="home-banner-slide"
                role="group"
                aria-roledescription="slide"
                aria-label={`${index + 1} dari ${slides.length}`}
                aria-live={paused || reducedMotion ? 'polite' : 'off'}
                className="neo-banner-slide relative grid min-h-[330px] min-w-0 gap-8 p-6 sm:p-9 lg:grid-cols-[1.5fr_1fr] lg:items-center lg:gap-14 lg:px-10"
            >
                <div className="min-w-0">
                    <p className="mb-4 flex items-center gap-2 font-mono text-xs font-semibold tracking-[0.14em] text-lime uppercase">
                        <span className="h-px w-6 bg-lime" />
                        {slide.tag}
                    </p>
                    <h2 className="max-w-xl text-[28px] leading-tight font-semibold tracking-tight [overflow-wrap:anywhere] sm:text-[38px]">
                        {slide.title}
                    </h2>
                    <p className="mt-4 line-clamp-3 max-w-lg text-sm leading-7 whitespace-pre-line text-white/75">{slide.description}</p>
                    <Link
                        href={slide.href}
                        className="mt-6 inline-flex min-h-12 items-center justify-center gap-3 rounded-control bg-lime px-5 text-sm font-semibold text-ink hover:bg-white"
                    >
                        {slide.action}
                        <ArrowRightIcon className="size-4 shrink-0" aria-hidden="true" />
                    </Link>
                </div>
                <div className="hidden min-w-0 rounded-card border border-white/15 bg-white/5 p-6 md:block">
                    {slide.kind === 'lab' ? (
                        <>
                            <div className="flex items-center justify-between">
                                <ComputerDesktopIcon className="size-8 text-teal" aria-hidden="true" />
                                <span className="font-mono text-xs text-white/65">CBT CENTER</span>
                            </div>
                            <p className="mt-6">
                                <span className="font-mono text-5xl font-semibold text-lime">
                                    {summary.labs.total.toString().padStart(2, '0')}
                                </span>
                                <span className="ml-3 text-sm text-white/80">ruang lab</span>
                            </p>
                            <div className="mt-6 grid grid-cols-3 gap-2 border-t border-white/15 pt-4">
                                {[
                                    [summary.labs.available, 'Tersedia'],
                                    [summary.labs.inUse, 'Dipakai'],
                                    [summary.labs.maintenance, 'Perawatan'],
                                ].map(([value, label]) => (
                                    <div key={label}>
                                        <p className="font-mono text-xl">{value}</p>
                                        <p className="mt-1 text-xs text-white/65">{label}</p>
                                    </div>
                                ))}
                            </div>
                        </>
                    ) : slide.kind === 'status' ? (
                        <>
                            <SignalIcon className="size-8 text-teal" aria-hidden="true" />
                            <p className="mt-4 text-lg font-semibold">Kondisi layanan kampus</p>
                            <div className="mt-5 space-y-4">
                                {(
                                    [
                                        [summary.networks.online, summary.networks.total, 'Jaringan online'],
                                        [summary.apps.online, summary.apps.total, 'Aplikasi online'],
                                    ] as const
                                )
                                    .filter(([, total]) => total > 0)
                                    .map(([value, total, label]) => (
                                        <div key={label} className="flex items-center justify-between gap-3 border-t border-white/15 pt-3">
                                            <span className="text-sm text-white/75">{label}</span>
                                            <span className="font-mono text-lg text-lime">
                                                {value}
                                                <span className="text-sm text-white/60"> / {total}</span>
                                            </span>
                                        </div>
                                    ))}
                            </div>
                        </>
                    ) : slide.kind === 'help' ? (
                        <>
                            <CalendarDaysIcon className="size-8 text-teal" aria-hidden="true" />
                            <p className="mt-4 text-lg font-semibold">Dari laporan ke penanganan</p>
                            <ol className="mt-5 space-y-3">
                                {['Isi detail gangguan', 'Simpan kode tiket Anda', 'Pantau perkembangan tiket'].map((text, i) => (
                                    <li key={text} className="flex items-center gap-3 text-sm text-white/80">
                                        <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-white/10 font-mono text-xs text-lime">
                                            0{i + 1}
                                        </span>
                                        {text}
                                    </li>
                                ))}
                            </ol>
                        </>
                    ) : (
                        <>
                            <MegaphoneIcon className="size-10 text-lime" aria-hidden="true" />
                            <p className="mt-5 text-xl font-semibold">Informasi untuk Anda</p>
                            <p className="mt-3 text-sm leading-7 text-white/75">
                                Pengumuman layanan dari UPT TIK. Baca informasi lengkap sebelum menggunakan layanan kampus.
                            </p>
                            <a href="#pengumuman" className="mt-4 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-lime">
                                Semua pengumuman <ArrowRightIcon className="size-4" aria-hidden="true" />
                            </a>
                        </>
                    )}
                </div>
            </div>
            <div className="relative flex flex-wrap items-center justify-between gap-2 border-t border-white/10 px-5 py-2 sm:px-9">
                <div className="flex flex-wrap items-center" aria-label="Pilih banner">
                    {slides.map((item, i) => (
                        <button
                            key={item.id}
                            type="button"
                            aria-label={`Banner ${i + 1}: ${item.tag}`}
                            aria-current={index === i ? 'true' : undefined}
                            onClick={() => select(i)}
                            className="flex size-11 cursor-pointer items-center justify-center rounded-control"
                        >
                            <span className={`h-1.5 rounded-full ${index === i ? 'w-7 bg-lime' : 'w-2 bg-white/40'}`} />
                        </button>
                    ))}
                </div>
                <div className="flex items-center gap-1">
                    <span className="mr-2 font-mono text-xs text-white/65">
                        {String(index + 1).padStart(2, '0')} / {String(slides.length).padStart(2, '0')}
                    </span>
                    {!reducedMotion && (
                        <button
                            type="button"
                            onPointerDown={() => {
                                pointerPaused.current = paused;
                            }}
                            onClick={() => {
                                setPaused(!(pointerPaused.current ?? paused));
                                pointerPaused.current = null;
                            }}
                            aria-label={paused ? 'Putar banner otomatis' : 'Jeda banner otomatis'}
                            className="flex size-11 cursor-pointer items-center justify-center rounded-full hover:bg-white/10"
                        >
                            {paused ? <PlayIcon className="size-4" /> : <PauseIcon className="size-4" />}
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={() => select(index - 1)}
                        aria-label="Banner sebelumnya"
                        aria-controls="home-banner-slide"
                        className="flex size-11 cursor-pointer items-center justify-center rounded-full border border-white/25 hover:bg-white/10"
                    >
                        <ChevronLeftIcon className="size-4" />
                    </button>
                    <button
                        type="button"
                        onClick={() => select(index + 1)}
                        aria-label="Banner berikutnya"
                        aria-controls="home-banner-slide"
                        className="flex size-11 cursor-pointer items-center justify-center rounded-full border border-white/25 hover:bg-white/10"
                    >
                        <ChevronRightIcon className="size-4" />
                    </button>
                </div>
            </div>
        </section>
    );
}
