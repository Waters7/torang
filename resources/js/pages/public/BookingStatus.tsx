import BookingSummary from '@/components/BookingSummary';
import { inputClass } from '@/components/form/Field';
import PublicLayout from '@/layouts/PublicLayout';
import type { PublicBooking } from '@/types/public';
import { Head, Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

interface Props {
    query: string | null;
    booking: PublicBooking | null;
}

export default function BookingStatus({ query, booking }: Props) {
    const [code, setCode] = useState(query ?? '');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get('/cek-status', { kode: code.trim() }, { preserveState: true });
    };

    return (
        <PublicLayout>
            <Head title="Cek status pengajuan" />

            <div className="mx-auto max-w-2xl px-4 py-12 sm:py-16">
                <p className="eyebrow">Lab CBT · Status pengajuan</p>
                <h1 className="mt-3 text-[28px] leading-tight font-bold tracking-tight">Cek status pengajuan</h1>
                <p className="mt-3 text-muted">Masukkan kode pengajuan (format CBT-YYMM-XXXX) yang dikirim ke email Anda.</p>

                <form onSubmit={submit} className="mt-6 flex flex-col gap-3 sm:flex-row">
                    <label htmlFor="kode" className="sr-only">
                        Kode pengajuan
                    </label>
                    <input id="kode" name="kode" className={`${inputClass} font-mono uppercase`} placeholder="CBT-2610-XXXX" value={code} onChange={(e) => setCode(e.target.value)} autoComplete="off" />
                    <button type="submit" className="inline-flex min-h-11 items-center justify-center rounded-control bg-teal-text px-5 text-sm font-semibold text-white hover:bg-ink">
                        Cek status
                    </button>
                </form>

                <div className="mt-8" aria-live="polite">
                    {booking && <BookingSummary booking={booking} />}

                    {query && !booking && (
                        <div className="rounded-card border border-border bg-white p-6">
                            <p className="font-semibold text-ink">Kode {query} tidak ditemukan.</p>
                            <p className="mt-1 text-sm text-muted">Periksa kembali penulisan kode, atau hubungi helpdesk UPT TIK.</p>
                        </div>
                    )}
                </div>

                <p className="mt-8 text-sm text-muted">
                    Belum mengajukan?{' '}
                    <Link href="/ajukan" className="font-semibold text-teal-text hover:text-ink">
                        Ajukan pemakaian Lab CBT
                    </Link>
                </p>
            </div>
        </PublicLayout>
    );
}
