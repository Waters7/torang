import type { TicketProgressEntry } from '@/types/tickets';

const tones: Record<TicketProgressEntry['type'], string> = {
    plan: 'bg-amber-50 text-amber-800',
    action: 'bg-cyan-50 text-cyan-800',
    progress: 'bg-emerald-50 text-emerald-800',
};

export default function TicketProgressTimeline({ entries }: { entries: TicketProgressEntry[] }) {
    return <section className="mt-8" aria-labelledby="ticket-progress-heading">
        <p className="eyebrow">Riwayat penanganan</p>
        <h2 id="ticket-progress-heading" className="mt-2 text-xl font-bold">Rencana & perkembangan</h2>
        <p className="mt-2 text-sm text-muted">Catatan petugas dari awal hingga tindak lanjut terbaru. Diperbarui otomatis setiap 30 detik.</p>
        {entries.length === 0 ? <p className="mt-5 rounded-card border border-border bg-white p-5 text-sm text-muted">Belum ada catatan penanganan yang dibagikan. Rencana dan tindakan petugas akan muncul di sini.</p> :
            <ol className="mt-6 space-y-5 border-l-2 border-border pl-5 sm:pl-7">
                {entries.map(entry => <li key={entry.id} className="relative min-w-0">
                    <span aria-hidden="true" className="absolute -left-[27px] top-6 h-3 w-3 rounded-full bg-teal ring-4 ring-surface sm:-left-[35px]" />
                    <article className="rounded-card border border-border bg-white p-5 sm:p-6">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${tones[entry.type]}`}>{entry.label}</span>
                            <span className="text-xs text-muted">Status: {entry.status}</span>
                        </div>
                        <p className="mt-4 whitespace-pre-wrap text-sm leading-relaxed [overflow-wrap:anywhere]">{entry.body}</p>
                        <footer className="mt-4 border-t border-border pt-3 text-xs text-muted">
                            <p className="font-semibold [overflow-wrap:anywhere]">{entry.author}</p>
                            <time dateTime={entry.createdAt} className="mt-1 block">{new Date(entry.createdAt).toLocaleString('id-ID', { timeZone: 'Asia/Makassar', dateStyle: 'long', timeStyle: 'short' })} WITA</time>
                        </footer>
                    </article>
                </li>)}
            </ol>}
    </section>;
}
