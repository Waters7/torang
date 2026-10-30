import HomeBanner from '@/components/HomeBanner';
import { OverviewCards, ServiceCards } from '@/components/HomeServiceCards';
import LabNowCard from '@/components/LabNowCard';
import { AppStatusCard, NetworkCard } from '@/components/MonitorCards';
import PublicWifiCards from '@/components/PublicWifiCards';
import SectionHeading from '@/components/SectionHeading';
import StatusPill from '@/components/StatusPill';
import WeekScheduleTable, { ScheduleLegend } from '@/components/WeekScheduleTable';
import WeeklyDutyCards from '@/components/WeeklyDutyCards';
import PublicLayout from '@/layouts/PublicLayout';
import { formatLongDate, formatTime, useNow } from '@/lib/time';
import type {
    AppStatus,
    DisplaySettings,
    LabNow,
    NetworkStatus,
    PublicAnnouncement,
    PublicDutyWeek,
    PublicSummary,
    PublicWifiNetwork,
    WeekSchedule,
} from '@/types/public';
import { ArrowRightIcon, ClockIcon, MegaphoneIcon } from '@heroicons/react/24/outline';
import { Head, Link, usePage, usePoll } from '@inertiajs/react';

interface Props {
    announcements: PublicAnnouncement[];
    summary: PublicSummary;
    labsNow: LabNow[];
    week: WeekSchedule;
    dutyWeek: PublicDutyWeek;
    networks: NetworkStatus[];
    apps: AppStatus[];
    wifi: PublicWifiNetwork[];
    display: DisplaySettings;
}

export default function Home({ summary, labsNow, week, dutyWeek, networks, apps, wifi, display, announcements }: Props) {
    const { site } = usePage().props;
    const now = useNow(30_000);
    const stale =
        summary.lastCheckedAt &&
        now.getTime() - new Date(summary.lastCheckedAt).getTime() >
            Math.max(summary.intervalMinutes * 3 * 60, display.refreshSeconds * 2) * 1000;

    // Jadwal ikut diperbarui agar pergantian hari/minggu dan penerbitan petugas terlihat tanpa membuka ulang halaman.
    // `display` ikut dimuat agar sakelar tampilan di dashboard langsung berlaku tanpa membuka ulang halaman.
    usePoll(display.refreshSeconds * 1000, {
        only: ['summary', 'labsNow', 'week', 'dutyWeek', 'networks', 'apps', 'wifi', 'display', 'announcements'],
    });

    const showWifi = display.showWifi && wifi.length > 0;

    return (
        <PublicLayout>
            <Head title="Beranda" />
            <div className="mx-auto max-w-6xl px-4 pt-7 pb-14 sm:pt-9">
                <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">Portal layanan · {site.unit}</p>
                        <h1 className="mt-2 text-2xl font-semibold tracking-tight sm:text-[30px]">Layanan IT kampus, lebih mudah.</h1>
                        <p className="mt-2 text-sm text-muted">{site.institution}</p>
                    </div>
                    <div className="text-xs text-muted-soft sm:text-right">
                        <p>{formatLongDate(now)}</p>
                        <p className="mt-1.5 inline-flex items-center gap-1.5 font-mono">
                            <ClockIcon className="size-3.5" aria-hidden="true" />
                            {formatTime(now)} WITA
                        </p>
                    </div>
                </div>
                <HomeBanner announcements={announcements} summary={summary} />

                {announcements.length > 0 && (
                    <section id="pengumuman" aria-label="Pengumuman layanan" className="mt-4 scroll-mt-6 space-y-2">
                        {announcements.map((item) => (
                            <article
                                key={item.id}
                                className={`rounded-control border px-4 py-3 ${item.type === 'incident' ? 'border-offline/25 bg-offline-bg' : item.type === 'maintenance' ? 'border-slow/25 bg-slow-bg' : 'border-border bg-white'}`}
                            >
                                <details open={item.type === 'incident'}>
                                    <summary className="flex min-h-11 cursor-pointer flex-wrap items-center gap-x-3 gap-y-2 text-sm">
                                        <MegaphoneIcon className="size-5 shrink-0" aria-hidden="true" />
                                        <span className="text-xs font-semibold text-muted">{item.label}</span>
                                        <h2 className="min-w-0 flex-1 basis-48 font-semibold [overflow-wrap:anywhere]">{item.title}</h2>
                                        <span className="ml-auto text-xs text-teal-text underline">Detail informasi</span>
                                    </summary>
                                    <div className="pt-2 pb-1 sm:pl-8">
                                        <p className="text-sm leading-7 whitespace-pre-wrap [overflow-wrap:anywhere]">{item.body}</p>
                                        {item.endsAt && (
                                            <p className="mt-3 text-xs text-muted">
                                                Hingga {new Date(item.endsAt).toLocaleString('id-ID', { timeZone: 'Asia/Makassar' })} WITA
                                            </p>
                                        )}
                                    </div>
                                </details>
                            </article>
                        ))}
                    </section>
                )}

                <div className="mt-9">
                    <ServiceCards />
                </div>

                <section aria-labelledby="judul-ringkasan" className="mt-10">
                    <div className="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 id="judul-ringkasan" className="text-xl font-semibold tracking-tight">
                            Sekilas layanan kampus
                        </h2>
                        <span className="text-xs text-muted-soft">Halaman diperbarui tiap {display.refreshSeconds} detik</span>
                    </div>
                    <OverviewCards summary={summary} dutyWeek={dutyWeek} />
                    <p className={`mt-3 text-xs leading-6 ${stale ? 'text-slow-text' : 'text-muted-soft'}`}>
                        {summary.lastCheckedAt ? (
                            <>
                                Pemeriksaan monitor terakhir:{' '}
                                {new Date(summary.lastCheckedAt).toLocaleString('id-ID', {
                                    timeZone: 'Asia/Makassar',
                                    day: 'numeric',
                                    month: 'short',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                })}{' '}
                                WITA.{stale && ' Data pemeriksaan belum diperbarui; kondisi layanan mungkin telah berubah.'}
                            </>
                        ) : (
                            'Belum ada hasil pemeriksaan monitor.'
                        )}
                    </p>
                </section>

                <nav aria-label="Jelajahi informasi beranda" className="mt-7 flex flex-wrap gap-2 border-y border-border py-3">
                    {(
                        [
                            ['#jadwal', 'Kondisi lab', true],
                            ['#jadwal-minggu', 'Jadwal minggu ini', true],
                            ['#petugas', 'Petugas minggu ini', true],
                            ['#wifi', 'WiFi kampus', showWifi],
                            ['#jaringan', 'Jaringan', display.showNetworks],
                            ['#aplikasi', 'Aplikasi', display.showApps],
                        ] as const
                    )
                        .filter(([, , visible]) => visible)
                        .map(([href, label]) => (
                            <a
                                key={href}
                                href={href}
                                className="inline-flex min-h-11 items-center rounded-full border border-border bg-white px-4 text-xs font-semibold text-muted hover:border-teal hover:text-teal-text"
                            >
                                {label}
                            </a>
                        ))}
                </nav>

                <section id="jadwal" aria-labelledby="judul-lab" className="mt-10 scroll-mt-6">
                    <SectionHeading
                        id="judul-lab"
                        eyebrow="CBT Center · Hari ini"
                        title="Kondisi Lab CBT"
                        description="Lihat kegiatan yang berlangsung dan ketersediaan setiap ruang."
                        aside={
                            <Link href="/ajukan" className="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-teal-text">
                                Ajukan pemakaian <ArrowRightIcon className="size-4" aria-hidden="true" />
                            </Link>
                        }
                    />
                    <div className="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {labsNow.map((lab) => (
                            <LabNowCard key={lab.id} lab={lab} now={now} />
                        ))}
                    </div>
                    {!labsNow.length && (
                        <p className="mt-5 rounded-card border border-border bg-white p-6 text-sm text-muted">
                            Informasi Lab CBT belum tersedia.
                        </p>
                    )}
                </section>

                <section id="jadwal-minggu" aria-labelledby="judul-minggu" className="mt-12 min-w-0 scroll-mt-6">
                    <SectionHeading
                        id="judul-minggu"
                        eyebrow="Rencanakan aktivitas Anda"
                        title="Jadwal lab minggu ini"
                        description={`${week.days[0]?.shortDate ?? ''} – ${week.days.at(-1)?.shortDate ?? ''}`}
                        aside={
                            <Link href="/jadwal" className="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-teal-text">
                                Kalender bulanan <ArrowRightIcon className="size-4" aria-hidden="true" />
                            </Link>
                        }
                    />
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <ScheduleLegend />
                        <p className="text-xs text-muted-soft lg:hidden">Geser tabel untuk melihat semua hari →</p>
                    </div>
                    <div className="mt-4 min-w-0">
                        <WeekScheduleTable week={week} />
                    </div>
                </section>

                <div className="mt-10">
                    <WeeklyDutyCards week={dutyWeek} />
                </div>

                {showWifi && (
                    <section id="wifi" aria-labelledby="judul-wifi" className="mt-12 scroll-mt-6 border-t border-border pt-10">
                        <SectionHeading
                            id="judul-wifi"
                            eyebrow="Akses internet"
                            title="WiFi kampus"
                            description="Jaringan WiFi yang dapat Anda gunakan beserta passwordnya."
                        />
                        <div className="mt-5">
                            <PublicWifiCards networks={wifi} />
                        </div>
                    </section>
                )}

                {display.showNetworks && (
                    <section id="jaringan" aria-labelledby="judul-jaringan" className="mt-12 scroll-mt-6 border-t border-border pt-10">
                        <SectionHeading
                            id="judul-jaringan"
                            eyebrow="Konektivitas kampus"
                            title="Jaringan per jurusan"
                            description="Warna bar menunjukkan kondisi jaringan selama 24 jam terakhir."
                            aside={
                                <div className="flex flex-wrap gap-2">
                                    <StatusPill tone="online">{summary.networks.online} Online</StatusPill>
                                    <StatusPill tone="slow">{summary.networks.slow} Lambat</StatusPill>
                                    <StatusPill tone="offline">{summary.networks.offline} Offline</StatusPill>
                                    {summary.networks.maintenance > 0 && (
                                        <StatusPill tone="slow">{summary.networks.maintenance} Maintenance</StatusPill>
                                    )}
                                </div>
                            }
                        />
                        <div className="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {networks.map((monitor) => (
                                <NetworkCard key={monitor.id} monitor={monitor} showBars={display.showHistoryBars} />
                            ))}
                        </div>
                        {!networks.length && (
                            <p className="mt-5 rounded-card border border-border bg-white p-6 text-sm text-muted">
                                Informasi jaringan belum tersedia.
                            </p>
                        )}
                    </section>
                )}

                {display.showApps && (
                    <section id="aplikasi" aria-labelledby="judul-aplikasi" className="mt-10 scroll-mt-6">
                        <SectionHeading
                            id="judul-aplikasi"
                            eyebrow="Layanan digital"
                            title="Aplikasi kampus"
                            description="Kondisi aplikasi dan riwayat ketersediaan 30 hari terakhir."
                            aside={
                                <Link href="/status" className="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-teal-text">
                                    Riwayat & insiden <ArrowRightIcon className="size-4" aria-hidden="true" />
                                </Link>
                            }
                        />
                        <div className="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {apps.map((monitor) => (
                                <AppStatusCard key={monitor.id} monitor={monitor} showBars={display.showHistoryBars} />
                            ))}
                        </div>
                        {!apps.length && (
                            <p className="mt-5 rounded-card border border-border bg-white p-6 text-sm text-muted">
                                Informasi aplikasi belum tersedia.
                            </p>
                        )}
                    </section>
                )}

                <aside className="mt-10 flex flex-wrap items-center justify-between gap-5 rounded-card bg-faint p-6">
                    <div>
                        <h2 className="font-semibold">Layanan bermasalah atau perlu bantuan?</h2>
                        <p className="mt-1 text-sm leading-6 text-muted">Sampaikan kendala Anda kepada tim {site.unit}.</p>
                    </div>
                    <Link
                        href="/lapor"
                        className="inline-flex min-h-11 items-center gap-2 rounded-control bg-teal-text px-5 text-sm font-semibold text-white hover:bg-ink"
                    >
                        Lapor gangguan <ArrowRightIcon className="size-4" aria-hidden="true" />
                    </Link>
                </aside>
            </div>
        </PublicLayout>
    );
}
