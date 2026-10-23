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
