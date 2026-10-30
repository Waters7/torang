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

export type MonitorStatusValue = 'up' | 'degraded' | 'down' | 'unknown' | 'paused' | 'maintenance';

export interface StatusCount {
    online: number;
    slow: number;
    offline: number;
    maintenance: number;
    total: number;
}

/** Keterangan maintenance dari petugas (null bila tidak sedang maintenance). */
export interface MonitorMaintenance {
    note: string | null;
    until: string | null;
}

/** SSID yang dipilih petugas tampil di halaman depan (password null untuk jaringan Open). */
export interface PublicWifiNetwork {
    id: number;
    ssid: string;
    location: string;
    audience: string;
    security: string;
    password: string | null;
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

export interface PublicAnnouncement {
    id: number;
    title: string;
    body: string;
    type: string;
    label: string;
    endsAt: string | null;
}

export interface PublicDutyWeek {
    published: boolean;
    start: string;
    end: string;
    label: string;
    staffCount: number;
    days: {
        date: string;
        label: string;
        shortDate: string;
        isToday: boolean;
        slots: { shift: string; start: string; end: string; coverage: string; staff: string[] }[];
    }[];
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
    maintenance: MonitorMaintenance | null;
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
    showNetworks: boolean;
    showApps: boolean;
    showWifi: boolean;
}
