import PublicLayout from '@/layouts/PublicLayout';
import { Head, usePage } from '@inertiajs/react';

export default function Home() {
    const { site } = usePage().props;

    return (
        <PublicLayout>
            <Head title="Beranda" />

            <section className="mx-auto max-w-6xl px-4 py-16 sm:py-20">
                <p className="eyebrow">
                    {site.unit} · {site.institution}
                </p>
                <h1 className="mt-3 max-w-3xl text-3xl font-bold tracking-tight sm:text-[40px] sm:leading-tight">
                    Pusat layanan IT {site.institution}
                </h1>
                <p className="mt-4 max-w-2xl text-base leading-relaxed text-muted">
                    Penjadwalan dan pengajuan Lab CBT, jadwal petugas, status jaringan per jurusan, status aplikasi kampus, serta helpdesk
                    dalam satu tempat.
                </p>

                <div className="mt-10 rounded-card border border-dashed border-border bg-white p-6 text-sm text-muted-soft">
                    Ringkasan status layanan, Lab CBT yang sedang berlangsung, dan jadwal minggu ini akan tampil di sini.
                </div>
            </section>
        </PublicLayout>
    );
}
