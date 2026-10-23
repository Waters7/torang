import StatusPill, { bookingStatusTone } from '@/components/StatusPill';
import type { PublicBooking } from '@/types/public';

/** Ringkasan pengajuan untuk halaman publik (tanpa data pribadi pemohon). */
export default function BookingSummary({ booking }: { booking: PublicBooking }) {
    const rows: [string, string][] = [
        ['Kegiatan', booking.activityName],
        ['Jenis', booking.activityType],
        ['Penyelenggara', booking.organizer ?? '-'],
        ['Lab', booking.lab],
        ['Tanggal', booking.dateLabel],
        ['Jam', `${booking.startTime}–${booking.endTime} WITA`],
        ['Peserta', `${booking.participantsCount} orang`],
        ['Diajukan', booking.submittedAt],
    ];

    return (
        <div className="rounded-card border border-border bg-white p-5 sm:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="font-mono text-lg font-semibold tracking-wider text-ink">{booking.code}</p>
                <StatusPill tone={bookingStatusTone[booking.status.value]}>{booking.status.label}</StatusPill>
            </div>

            <dl className="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-[140px_1fr]">
                {rows.map(([label, value]) => (
                    <div key={label} className="contents">
                        <dt className="text-muted-soft">{label}</dt>
                        <dd className="font-medium text-ink">{value}</dd>
                    </div>
                ))}
            </dl>

            {booking.rejectionReason && (
                <div className="mt-5 rounded-control bg-offline-bg p-4 text-sm text-offline-text">
                    <p className="font-semibold">Alasan penolakan</p>
                    <p className="mt-1">{booking.rejectionReason}</p>
                </div>
            )}
        </div>
    );
}
