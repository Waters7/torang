import PublicLayout from '@/layouts/PublicLayout';
import FullCalendar, { type EventInput, type EventSourceFuncInfo } from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/react/daygrid';
import listPlugin from '@fullcalendar/react/list';
import idLocale from '@fullcalendar/react/locales/id';
import classicThemePlugin from '@fullcalendar/react/themes/classic';
import '@fullcalendar/react/skeleton.css';
import '@fullcalendar/react/themes/classic/theme.css';
import '@fullcalendar/react/themes/classic/palette.css';
import { ArrowRightIcon } from '@heroicons/react/24/outline';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    labs: { id: number; name: string; color: string }[];
}

/** Kalender bulanan publik (pengajuan disetujui + maintenance), daftar per hari di layar kecil. */
export default function Schedule({ labs }: Props) {
    const [isSmall] = useState(() => window.matchMedia('(max-width: 639px)').matches);
    const [error, setError] = useState(false);

    return (
        <PublicLayout>
            <Head title="Jadwal Lab CBT" />

            <div className="mx-auto max-w-6xl px-4 py-10 sm:py-14">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">Lab CBT · Kalender</p>
                        <h1 className="mt-3 text-[28px] leading-tight font-bold tracking-tight">Jadwal pemakaian Lab CBT</h1>
                        <p className="mt-2 max-w-2xl text-sm text-muted">Kegiatan yang sudah disetujui dan jadwal maintenance. Pengajuan yang masih menunggu tidak ditampilkan.</p>
                    </div>
                    <Link href="/ajukan" className="inline-flex min-h-11 items-center gap-2 rounded-control bg-teal-text px-5 text-sm font-semibold text-white hover:bg-ink">
                        Ajukan Pemakaian
                        <ArrowRightIcon className="size-4" aria-hidden="true" />
                    </Link>
                </div>

                <ul className="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted" aria-label="Warna lab">
                    {labs.map((lab) => (
                        <li key={lab.id} className="flex items-center gap-2">
                            <span className="size-3 rounded-full" style={{ backgroundColor: lab.color }} aria-hidden="true" />
                            {lab.name}
                        </li>
                    ))}
                </ul>

                {error && (
                    <p className="mt-4 rounded-control bg-slow-bg p-3 text-sm text-slow-text" role="alert">
                        Jadwal gagal dimuat. Coba muat ulang halaman.
                    </p>
                )}

                <div className="torang-calendar mt-6 rounded-card border border-border bg-white p-3 sm:p-5">
                    <FullCalendar
                        plugins={[dayGridPlugin, listPlugin, classicThemePlugin]}
                        locale={idLocale}
                        initialView={isSmall ? 'listMonth' : 'dayGridMonth'}
                        firstDay={1}
                        height="auto"
                        dayMaxEvents={3}
                        displayEventEnd
                        eventTimeFormat={{ hour: '2-digit', minute: '2-digit', hour12: false }}
                        headerToolbar={{ left: 'prev,next today', center: 'title', right: isSmall ? '' : 'dayGridMonth,listMonth' }}
                        noEventsText="Tidak ada kegiatan pada periode ini"
                        events={async (info: EventSourceFuncInfo): Promise<EventInput[]> => {
                            const params = new URLSearchParams({ start: info.startStr, end: info.endStr });
                            const response = await fetch(`/api/public/events?${params.toString()}`, { headers: { Accept: 'application/json' } });

                            if (!response.ok) {
                                setError(true);

                                return [];
                            }

                            setError(false);

                            return response.json();
                        }}
                    />
                </div>
            </div>
        </PublicLayout>
    );
}
