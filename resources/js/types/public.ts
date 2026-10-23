/** Identitas & kontak helpdesk, dibagikan ke semua halaman publik (HandleInertiaRequests). */
export interface SiteProps {
    institution: string;
    unit: string;
    helpdesk: {
        whatsapp: string | null;
        email: string | null;
        serviceHours: string | null;
    };
}

export interface Option<T extends string | number = string> {
    value: T;
    label: string;
}

export type LabStatusValue = 'active' | 'maintenance' | 'inactive';

export interface PublicLab {
    id: number;
    code: string;
    name: string;
    location: string | null;
    capacity: number;
    readyPcs: number;
    status: { value: LabStatusValue; label: string };
    color: string;
}

export type BookingStatusValue = 'pending' | 'approved' | 'rejected' | 'completed' | 'cancelled';

/** Data pengajuan untuk publik — tanpa data pribadi pemohon. */
export interface PublicBooking {
    code: string;
    status: { value: BookingStatusValue; label: string };
    activityName: string;
    activityType: string;
    organizer: string | null;
    lab: string;
    date: string;
    dateLabel: string;
    startTime: string;
    endTime: string;
    participantsCount: number;
    rejectionReason: string | null;
    submittedAt: string;
}

export interface LabSlotAvailability {
    labId: number;
    status: 'available' | 'busy' | LabStatusValue;
    busy: { start: string; end: string }[];
}

export interface BookingRulesProps {
    minDate: string;
    minDaysAhead: number;
    openTime: string;
    closeTime: string;
}

export type MonitorStatusValue = 'up' | 'degraded' | 'down' | 'unknown' | 'paused';

export interface StatusCount {
    online: number;
    slow: number;
    offline: number;
    total: number;
}

export interface PublicSummary {
    networks: StatusCount;
    apps: StatusCount;
    labs: { inUse: number; available: number; maintenance: number; total: number };
    lastCheckedAt: string | null;
    intervalMinutes: number;
    generatedAt: string;
}

export interface BookingSlot {
    activityName: string;
    activityType: string;
    organizer: string | null;
    start: string;
    end: string;
    startsAt: string;
    endsAt: string;
    participants: number;
}

export interface LabNow {
    id: number;
    code: string;
    name: string;
    location: string | null;
    color: string;
    readyPcs: number;
    state: 'ongoing' | 'available' | 'maintenance';
    current: BookingSlot | null;
    next: BookingSlot | null;
    maintenanceNote: string | null;
    maintenanceUntil: string | null;
    staff: string[];
}

export type ScheduleBlockState = 'done' | 'ongoing' | 'scheduled' | 'maintenance';

export interface WeekSchedule {
    days: { date: string; label: string; dayLabel: string; shortDate: string; isToday: boolean }[];
    labs: { id: number; name: string; color: string; blocks: Record<string, (BookingSlot & { state: ScheduleBlockState })[]> }[];
}

export interface HourBar {
    label: string;
    status: MonitorStatusValue | null;
}

export interface DayBar {
    label: string;
    uptime: number | null;
    level: 'good' | 'warning' | 'bad' | null;
}

interface MonitorBase {
    id: number;
    name: string;
    status: { value: MonitorStatusValue; label: string };
    latencyMs: number | null;
    uptime30d: number | null;
}

export interface NetworkStatus extends MonitorBase {
    shortName: string | null;
    packetLoss: number | null;
    downSince: string | null;
    bars: HourBar[];
}

export interface AppStatus extends MonitorBase {
    category: string | null;
    bars: DayBar[];
}

export interface StatusPageMonitor extends MonitorBase {
    category: string | null;
    uptime90d: number | null;
    bars: DayBar[];
}

export interface PublicIncident {
    id: number;
    monitor: string;
    group: string;
    startedAt: string;
    resolvedAt: string | null;
    duration: string;
    ongoing: boolean;
}

export interface DisplaySettings {
    refreshSeconds: number;
    showHistoryBars: boolean;
    goodPercent: number;
    warningPercent: number;
}
