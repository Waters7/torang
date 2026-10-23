import BookingSummary from '@/components/BookingSummary';
import StatusPill from '@/components/StatusPill';
import PublicLayout from '@/layouts/PublicLayout';
import type { PublicBooking } from '@/types/public';
import { Head, Link } from '@inertiajs/react';

export default function BookingSent({ booking }: { booking: PublicBooking }) {
    return (
        <PublicLayout>
            <Head title="Pengajuan terkirim" />

            <div className="mx-auto max-w-2xl px-4 py-12 sm:py-16">
                <StatusPill tone="online">Pengajuan terkirim</StatusPill>
                <h1 className="mt-4 text-[28px] leading-tight font-bold tracking-tight">Terima kasih, pengajuan Anda sudah kami terima.</h1>
                <p className="mt-3 text-muted">Simpan kode di bawah ini. Kami juga mengirimkannya ke email Anda bersama info status pengajuan.</p>

                <div className="mt-8 rounded-card bg-ink-dark px-6 py-8 text-center">
                    <p className="font-mono text-xs font-semibold tracking-[0.14em] text-teal uppercase">Kode pengajuan</p>
                    <p className="mt-3 font-mono text-3xl font-semibold tracking-[0.12em] break-all text-white sm:text-4xl">{booking.code}</p>
                </div>

                <div className="mt-6">
                    <BookingSummary booking={booking} />
                </div>

                <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                    <Link href="/" className="inline-flex min-h-11 items-center justify-center rounded-control border border-teal-text px-5 text-sm font-semibold text-teal-text hover:bg-faint">
                        Kembali ke beranda
                    </Link>
                    <Link href="/ajukan" className="inline-flex min-h-11 items-center justify-center rounded-control bg-teal-text px-5 text-sm font-semibold text-white hover:bg-ink">
                        Ajukan kegiatan lain
                    </Link>
                </div>
            </div>
        </PublicLayout>
    );
}
