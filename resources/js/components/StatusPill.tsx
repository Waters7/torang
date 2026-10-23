import type { BookingStatusValue } from '@/types/public';
import type { ReactNode } from 'react';

export type StatusTone = 'online' | 'slow' | 'offline' | 'available' | 'nodata';

const tones: Record<StatusTone, { pill: string; dot: string }> = {
    online: { pill: 'bg-online-bg text-online-text', dot: 'bg-online' },
    slow: { pill: 'bg-slow-bg text-slow-text', dot: 'bg-slow' },
    offline: { pill: 'bg-offline-bg text-offline-text', dot: 'bg-offline' },
    available: { pill: 'bg-available-bg text-available-text', dot: 'bg-available' },
    nodata: { pill: 'bg-nodata-bg text-nodata-text', dot: 'bg-nodata' },
};

export const bookingStatusTone: Record<BookingStatusValue, StatusTone> = {
    pending: 'slow',
    approved: 'online',
    rejected: 'offline',
    completed: 'nodata',
    cancelled: 'nodata',
};

/** Pill status — warna selalu disertai label teks (SPEC §11.1). */
export default function StatusPill({ tone, children, className = '' }: { tone: StatusTone; children: ReactNode; className?: string }) {
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ${tones[tone].pill} ${className}`}>
            <span className={`size-2 rounded-full ${tones[tone].dot}`} aria-hidden="true" />
            {children}
        </span>
    );
}
