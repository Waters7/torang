import { useEffect, useState } from 'react';

const TIME_ZONE = 'Asia/Makassar';

function parts(date: Date, options: Intl.DateTimeFormatOptions): Record<string, string> {
    return Object.fromEntries(
        new Intl.DateTimeFormat('id-ID', { timeZone: TIME_ZONE, ...options }).formatToParts(date).map((part) => [part.type, part.value]),
    );
}

/** "09:30" di zona WITA (titik dua, sesuai SPEC "HH:MM WITA"). */
export function formatTime(value: Date | string): string {
    const p = parts(new Date(value), { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });

    return `${p.hour}:${p.minute}`;
}

/** "Sabtu, 24 Oktober 2026" di zona WITA. */
export function formatLongDate(value: Date | string): string {
    return new Intl.DateTimeFormat('id-ID', { timeZone: TIME_ZONE, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(value));
}

/** "99,9%" */
export function formatPercent(value: number | null, digits = 1): string {
    return value === null ? '–' : `${value.toLocaleString('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits })}%`;
}

/** Waktu sekarang yang diperbarui berkala di klien. */
export function useNow(intervalMs = 30_000): Date {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(new Date()), intervalMs);

        return () => window.clearInterval(timer);
    }, [intervalMs]);

    return now;
}
