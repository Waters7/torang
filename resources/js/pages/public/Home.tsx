import LabNowCard from '@/components/LabNowCard';
import { AppStatusRow, NetworkCard } from '@/components/MonitorCards';
import SectionHeading from '@/components/SectionHeading';
import StatusPill from '@/components/StatusPill';
import SummaryCard from '@/components/SummaryCard';
import WeekScheduleTable, { ScheduleLegend } from '@/components/WeekScheduleTable';
import PublicLayout from '@/layouts/PublicLayout';
import { formatTime, useNow } from '@/lib/time';
import type { AppStatus, DisplaySettings, LabNow, NetworkStatus, PublicSummary, WeekSchedule } from '@/types/public';
import { ArrowRightIcon } from '@heroicons/react/24/outline';
import { Head, Link, usePage, usePoll } from '@inertiajs/react';

interface Props {
    summary: PublicSummary;
    labsNow: LabNow[];
    week: WeekSchedule;
    networks: NetworkStatus[];
    apps: AppStatus[];
    display: DisplaySettings;
}

export default function Home({ summary, labsNow, week, networks, apps, display }: Props) {
    const { site } = usePage().props;
    const now = useNow(30_000);

    // Partial reload berkala: hanya bagian status, jadwal minggu tidak ikut dimuat ulang.
    usePoll(display.refreshSeconds * 1000, { only: ['summary', 'labsNow', 'networks', 'apps'] });

    return (
        <PublicLayout>
            <Head title="Beranda" />

            {/* Hero */}
            <section className="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:py-14 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:items-center">
                <div className="min-w-0">
                    <StatusPill tone="available" wrap>
                        Diperbarui {formatTime(summary.generatedAt)} WITA · cek otomatis tiap {summary.intervalMinutes} menit
                    </StatusPill>
                    <h1 className="mt-5 text-3xl leading-tight font-bold tracking-tight sm:text-[42px]">Pusat layanan IT {site.institution}</h1>
                    <p className="mt-4 max-w-xl text-base leading-relaxed text-muted">
                        Lihat Lab CBT yang sedang dipakai, jadwal minggu ini, serta status jaringan jurusan dan aplikasi kampus — langsung dari {site.unit}.
                    </p>
                    <div className="mt-7 flex flex-col gap-3 sm:flex-row">
                        <Link href="/ajukan" className="inline-flex min-h-11 items-center justify-center gap-2 rounded-control bg-teal-text px-5 text-sm font-semibold text-white hover:bg-ink">
                            Ajukan Pemakaian Lab CBT
                            <ArrowRightIcon className="size-4" aria-hidden="true" />
                        </Link>
                        <a href="#jadwal-minggu" className="inline-flex min-h-11 items-center justify-center rounded-control border border-teal-text px-5 text-sm font-semibold text-teal-text hover:bg-faint">
                            Lihat Jadwal Minggu Ini
                        </a>
                    </div>
                </div>
                <SummaryCard summary={summary} />
            </section>

            {/* Sedang berlangsung */}
            <section id="jadwal" aria-labelledby="judul-lab" className="mx-auto max-w-6xl scroll-mt-6 px-4 py-8">
                <SectionHeading id="judul-lab" eyebrow="Lab CBT · Hari ini" title="Sedang berlangsung" description="Kondisi tiap Lab CBT saat ini beserta petugas jaga." />
                <div className="mt-6 grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(260px,1fr))]">
                    {labsNow.map((lab) => (
                        <LabNowCard key={lab.id} lab={lab} now={now} />
                    ))}
                </div>
            </section>

            {/* Jadwal minggu berjalan */}
            <section id="jadwal-minggu" aria-labelledby="judul-minggu" className="mx-auto max-w-6xl scroll-mt-6 px-4 py-8">
                <SectionHeading id="judul-minggu" eyebrow="Jadwal minggu ini" title="Jadwal Lab CBT" aside={<ScheduleLegend />} />
                <div className="mt-6">
                    <WeekScheduleTable week={week} />
                </div>
                <Link href="/jadwal" className="mt-4 inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-teal-text hover:text-ink">
                    Lihat kalender bulanan
                    <ArrowRightIcon className="size-4" aria-hidden="true" />
                </Link>
            </section>

            {/* Status jaringan per jurusan */}
            <section id="jaringan" aria-labelledby="judul-jaringan" className="mx-auto max-w-6xl scroll-mt-6 px-4 py-8">
                <SectionHeading
                    id="judul-jaringan"
                    eyebrow={`Monitoring · ping tiap ${summary.intervalMinutes} menit`}
                    title="Status jaringan per jurusan"
                    aside={
                        <div className="flex flex-wrap gap-2">
                            <StatusPill tone="online">{summary.networks.online} Online</StatusPill>
                            <StatusPill tone="slow">{summary.networks.slow} Lambat</StatusPill>
                            <StatusPill tone="offline">{summary.networks.offline} Offline</StatusPill>
                        </div>
                    }
                />
                <div className="mt-6 grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(250px,1fr))]">
                    {networks.map((monitor) => (
                        <NetworkCard key={monitor.id} monitor={monitor} showBars={display.showHistoryBars} />
                    ))}
                </div>
                <p className="mt-3 text-xs text-muted-soft">Bar menunjukkan status 24 jam terakhir per jam. Alamat IP tidak ditampilkan untuk publik.</p>
            </section>

            {/* Status aplikasi kampus */}
            <section id="aplikasi" aria-labelledby="judul-aplikasi" className="mx-auto max-w-6xl scroll-mt-6 px-4 pt-8 pb-14">
                <SectionHeading id="judul-aplikasi" eyebrow="Monitoring · aplikasi" title="Status aplikasi kampus" description="Bar menunjukkan uptime harian 30 hari terakhir." />
                <ul className="mt-6 divide-y divide-border rounded-card border border-border bg-white">
                    {apps.map((monitor) => (
                        <AppStatusRow key={monitor.id} monitor={monitor} showBars={display.showHistoryBars} />
                    ))}
                </ul>
                <Link href="/status" className="mt-4 inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-teal-text hover:text-ink">
                    Riwayat 90 hari & insiden
                    <ArrowRightIcon className="size-4" aria-hidden="true" />
                </Link>
            </section>
        </PublicLayout>
    );
}
