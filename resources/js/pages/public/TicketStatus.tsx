import { Head, Link, router, usePoll } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import PublicLayout from '@/layouts/PublicLayout';
import { inputClass } from '@/components/form/Field';

import TicketProgressTimeline from '@/components/TicketProgressTimeline';
import type { PublicTicket } from '@/types/tickets';

export default function TicketStatus({ query, ticket, ratingUrl, sent }: { query: string; ticket: PublicTicket | null; ratingUrl: string | null; sent: boolean }) {
    const [code, setCode] = useState(query);
    usePoll(30_000, { only: ['ticket'] });
    const submit = (e: FormEvent) => { e.preventDefault(); router.get('/cek-tiket', { kode: code }); };
    return <PublicLayout><Head title="Status & perkembangan tiket"><meta name="referrer" content="no-referrer" /><meta name="robots" content="noindex, nofollow" /></Head><div className="mx-auto max-w-3xl px-4 py-12">
        <p className="eyebrow">Helpdesk UPT TIK</p><h1 className="mt-3 text-3xl font-bold">{sent ? 'Laporan berhasil diterima' : 'Cek status & perkembangan tiket'}</h1>
        <p className="mt-3 text-muted">Masukkan nomor tiket untuk melihat status, petugas, rencana kerja, dan perkembangan penanganan.</p>
        <form onSubmit={submit} className="mt-6 flex flex-col gap-3 sm:flex-row"><label className="grow"><span className="sr-only">Kode tiket</span><input className={`${inputClass} font-mono`} placeholder="TIK-YYMM-XXXXXXXXXXXX" value={code} onChange={e => setCode(e.target.value)} maxLength={40} required /></label><button className="rounded-control bg-teal-text px-5 py-3 font-semibold text-white">Cek status</button></form>
        <div aria-live="polite" className="mt-6">{ticket ? <div className="space-y-3 rounded-card border border-border bg-white p-6"><p className="break-all font-mono text-lg font-bold">{ticket.code}</p><p className="text-xl font-semibold text-teal-text">{ticket.status}</p><p>Kategori: {ticket.category}</p><p className="text-sm text-muted">Diterima {new Date(ticket.createdAt).toLocaleString('id-ID', { timeZone: 'Asia/Makassar' })} WITA</p>{ticket.resolutionNotes && <div className="border-t border-border pt-3"><p className="text-sm font-semibold">Pekerjaan yang dilakukan</p><p className="mt-1 whitespace-pre-wrap text-sm">{ticket.resolutionNotes}</p></div>}</div> : query && <p>Kode tiket tidak ditemukan. Periksa kembali kode Anda.</p>}</div>
        {ticket && <><p className="mt-5 text-sm text-muted [overflow-wrap:anywhere]">Petugas: <strong>{ticket.assigneeName ?? 'Belum ditugaskan'}</strong></p><TicketProgressTimeline entries={ticket.progress} /></>}
        {ratingUrl && <div className="mt-6 rounded-card bg-ink-dark p-6 text-white"><h2 className="font-semibold">Simpan tautan pribadi Anda</h2><p className="mt-2 text-sm text-white/75">Tautan ini untuk membaca tanggapan khusus pelapor dan memberi penilaian setelah tiket selesai. Rencana dan perkembangan publik juga dapat dilihat dengan nomor tiket. Simpan tautan pribadi ini dan jangan bagikan kepada orang lain.</p><a href={ratingUrl} className="mt-4 block break-all text-sm underline">{ratingUrl}</a></div>}
        <Link href="/lapor" className="mt-8 inline-block font-semibold text-teal-text">Lapor gangguan lain →</Link>
    </div></PublicLayout>;
}
