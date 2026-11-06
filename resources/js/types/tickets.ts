export interface TicketProgressEntry {
    id: number;
    type: 'plan' | 'action' | 'progress';
    label: string;
    body: string;
    status: string;
    author: string;
    createdAt: string;
}

export interface PublicTicket {
    code: string;
    category: string;
    status: string;
    finished: boolean;
    createdAt: string;
    resolvedAt: string | null;
    resolutionNotes: string | null;
    assigneeName: string | null;
    progress: TicketProgressEntry[];
}
