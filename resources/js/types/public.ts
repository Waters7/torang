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
