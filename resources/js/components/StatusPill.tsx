import type { BookingStatusValue, MonitorStatusValue } from '@/types/public';
import type { ReactNode } from 'react';

export type StatusTone = 'online' | 'slow' | 'offline' | 'available' | 'nodata';

export const toneClasses: Record<StatusTone, { pill: string; dot: string }> = {
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

export const monitorStatusTone: Record<MonitorStatusValue, StatusTone> = {
    up: 'online',
    degraded: 'slow',
    down: 'offline',
    unknown: 'nodata',
    paused: 'nodata',
};

/**
 * Pill status — warna selalu disertai label teks (SPEC §11.1).
 * `wrap` untuk teks panjang (kalimat) agar boleh turun baris di ponsel; label pendek tetap satu baris.
 */
export default function StatusPill({ tone, children, className = '', wrap = false }: { tone: StatusTone; children: ReactNode; className?: string; wrap?: boolean }) {
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ${wrap ? 'max-w-full' : 'whitespace-nowrap'} ${toneClasses[tone].pill} ${className}`}>
            <span className={`size-2 shrink-0 rounded-full ${toneClasses[tone].dot}`} aria-hidden="true" />
            {children}
        </span>
    );
}
