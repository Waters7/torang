import {
    ArrowRightIcon,
    CalendarDaysIcon,
    ChatBubbleLeftRightIcon,
    ComputerDesktopIcon,
    LockClosedIcon,
    MagnifyingGlassIcon,
    SignalIcon,
    Squares2X2Icon,
    TvIcon,
    UserGroupIcon,
} from '@heroicons/react/24/outline';
import { Link } from '@inertiajs/react';
import type { PublicDutyWeek, PublicSummary } from '@/types/public';

const services = [
    {
        title: 'Ajukan Lab CBT',
        description: 'Pesan ruang untuk ujian dan kegiatan Anda.',
        href: '/ajukan',
        icon: ComputerDesktopIcon,
        color: 'bg-faint text-teal-text',
    },
    {
        title: 'Lihat jadwal lab',
        description: 'Temukan tanggal dan waktu yang tersedia.',
        href: '/jadwal',
        icon: CalendarDaysIcon,
        color: 'bg-available-bg text-available-text',
    },
    {
        title: 'Lapor gangguan',
        description: 'Kirim kendala IT untuk ditangani petugas.',
        href: '/lapor',
        icon: ChatBubbleLeftRightIcon,
        color: 'bg-slow-bg text-slow-text',
    },
    {
        title: 'Cek status layanan',
        description: 'Pantau jaringan dan aplikasi kampus.',
        href: '/status',
        icon: SignalIcon,
        color: 'bg-nodata-bg text-ink',
    },
];

export function ServiceCards() {
    return (
        <section id="layanan" aria-labelledby="judul-layanan" className="scroll-mt-6">
            <div className="mb-5 flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="judul-layanan" className="text-xl font-semibold tracking-tight">
                    Apa yang Anda perlukan?
                </h2>
                <p className="text-xs text-muted-soft">Layanan kampus dalam satu tempat</p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {services.map(({ icon: Icon, ...service }) => (
                    <Link
                        key={service.href}
                        href={service.href}
                        className="group flex flex-col rounded-card border border-border bg-white p-5 transition-colors hover:border-teal hover:bg-faint/40 motion-reduce:transition-none"
                    >
                        <div className="flex items-center justify-between">
                            <span className={`flex size-11 items-center justify-center rounded-control ${service.color}`}>
                                <Icon className="size-6" aria-hidden="true" />
                            </span>
                            <ArrowRightIcon className="size-4 text-muted-soft group-hover:text-teal-text" aria-hidden="true" />
                        </div>
                        <h3 className="mt-4 text-sm font-semibold">{service.title}</h3>
                        <p className="mt-2 text-xs leading-6 text-muted">{service.description}</p>
                    </Link>
                ))}
            </div>
            <a
                href="/admin/tv"
                className="group mt-5 flex flex-wrap items-center gap-4 rounded-card border border-border bg-white p-5 hover:border-teal"
            >
                <span className="flex size-12 shrink-0 items-center justify-center rounded-control bg-ink text-teal">
                    <TvIcon className="size-6" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1 basis-52">
                    <h3 className="font-semibold">Mode TV / NOC</h3>
                    <p className="mt-1 text-xs leading-6 text-muted">
                        Pantau jaringan, aplikasi, kegiatan lab, dan petugas dalam tampilan layar penuh.
                    </p>
                    <span className="mt-2 inline-flex items-center gap-1.5 text-xs text-muted-soft">
                        <LockClosedIcon className="size-3.5" aria-hidden="true" />
                        Login petugas
                    </span>
                </div>
                <span className="ml-auto inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-teal-text">
                    Buka Mode TV <ArrowRightIcon className="size-4" aria-hidden="true" />
                </span>
            </a>
            <div className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 rounded-control bg-faint px-4 py-2 text-xs">
                <span className="inline-flex items-center gap-2 text-muted">
                    <MagnifyingGlassIcon className="size-4" aria-hidden="true" />
                    Sudah mengajukan atau melapor?
                </span>
                <Link href="/cek-status" className="inline-flex min-h-11 items-center gap-2 font-semibold text-teal-text hover:underline">
                    Lacak pengajuan lab <ArrowRightIcon className="size-3.5" aria-hidden="true" />
                </Link>
                <Link href="/cek-tiket" className="inline-flex min-h-11 items-center gap-2 font-semibold text-teal-text hover:underline">
                    Lacak tiket gangguan <ArrowRightIcon className="size-3.5" aria-hidden="true" />
                </Link>
            </div>
        </section>
    );
}

export function OverviewCards({ summary, dutyWeek }: { summary: PublicSummary; dutyWeek: PublicDutyWeek }) {
    const items = [
        {
            label: 'Lab tersedia',
            value: summary.labs.available,
            suffix: `/ ${summary.labs.total}`,
            note: `${summary.labs.inUse} dipakai · ${summary.labs.maintenance} perawatan`,
            href: '#jadwal',
            icon: ComputerDesktopIcon,
            tone: 'text-teal-text bg-faint',
        },
        {
            label: 'Jaringan online',
            value: summary.networks.online,
            suffix: `/ ${summary.networks.total}`,
            note: `${summary.networks.slow} lambat · ${summary.networks.offline} offline`,
            href: '#jaringan',
            icon: SignalIcon,
            tone: 'text-available-text bg-available-bg',
        },
        {
            label: 'Aplikasi online',
            value: summary.apps.online,
            suffix: `/ ${summary.apps.total}`,
            note: `${summary.apps.slow} lambat · ${summary.apps.offline} offline`,
            href: '#aplikasi',
            icon: Squares2X2Icon,
            tone: 'text-teal-text bg-faint',
        },
        {
            label: 'Petugas minggu ini',
            value: dutyWeek.published ? dutyWeek.staffCount : '–',
            suffix: '',
            note: dutyWeek.published ? 'Lihat nama & shift petugas' : 'Jadwal belum diterbitkan',
            href: '#petugas',
            icon: UserGroupIcon,
            tone: 'text-slow-text bg-slow-bg',
        },
    ];

    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {items.map(({ icon: Icon, ...item }) => (
                <a key={item.href} href={item.href} className="group rounded-card border border-border bg-white p-5 hover:border-teal">
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-xs font-medium text-muted">{item.label}</p>
                        <Icon className={`size-8 rounded-lg p-1.5 ${item.tone}`} aria-hidden="true" />
                    </div>
                    <p className="mt-2 font-mono text-[32px] font-semibold leading-tight">
                        {item.value} <span className="text-base font-normal text-muted-soft">{item.suffix}</span>
                    </p>
                    <p className="mt-3 flex items-center justify-between gap-2 text-xs text-muted-soft">
                        {item.note}
                        <ArrowRightIcon className="size-3.5 shrink-0 group-hover:text-teal-text" aria-hidden="true" />
                    </p>
                </a>
            ))}
        </div>
    );
}
