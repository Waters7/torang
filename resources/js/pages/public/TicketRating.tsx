import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import PublicLayout from '@/layouts/PublicLayout';
import Turnstile from '@/components/Turnstile';
import { inputClass } from '@/components/form/Field';
import type { PublicTicket } from './TicketStatus';

export default function TicketRating({ ticket, rated, submitUrl, turnstileSiteKey, comments }: { ticket: PublicTicket; rated: boolean; submitUrl: string; turnstileSiteKey: string; comments: { body: string; at: string }[] }) {
    const [resetKey, setResetKey] = useState(0);
    const form = useForm({ rating: '', feedback: '', website: '', captcha_token: '' });
    const submit = (e: FormEvent) => { e.preventDefault(); form.post(submitUrl, { onError: () => setResetKey(k => k + 1) }); };
    return <PublicLayout><Head title="Tanggapan & penilaian tiket"><meta name="referrer" content="no-referrer" /></Head><div className="mx-auto max-w-2xl px-4 py-12">
        <p className="eyebrow">Tanggapan & penilaian</p><h1 className="mt-3 break-all font-mono text-xl font-bold">{ticket.code}</h1><p className="mt-3 font-semibold text-teal-text">{ticket.status}</p>
        {comments.map((c, i) => <div key={i} className="mt-4 rounded-card border border-border bg-white p-5"><p className="whitespace-pre-wrap">{c.body}</p><p className="mt-2 text-xs text-muted">{new Date(c.at).toLocaleString('id-ID', { timeZone: 'Asia/Makassar' })} WITA</p></div>)}
        {rated ? <p className="mt-6 rounded-card bg-faint p-6">Terima kasih. Penilaian Anda sudah tersimpan.</p> : !ticket.finished ? <p className="mt-6">Penilaian tersedia setelah tiket selesai ditangani.</p> : <form onSubmit={submit} className="mt-6 space-y-5 rounded-card border border-border bg-white p-6">
            <label className="block font-semibold">Seberapa puas Anda dengan layanan kami?<select className={`${inputClass} mt-2`} required value={form.data.rating} onChange={e => form.setData('rating', e.target.value)}><option value="">Pilih nilai</option>{['Sangat tidak puas', 'Tidak puas', 'Cukup', 'Puas', 'Sangat puas'].map((v, i) => <option key={i} value={i + 1}>{i + 1} — {v}</option>)}</select></label>
            <label className="block font-semibold">Masukan (opsional)<textarea className={`${inputClass} mt-2`} rows={4} maxLength={2000} value={form.data.feedback} onChange={e => form.setData('feedback', e.target.value)} /></label>
            <input className="hidden" tabIndex={-1} aria-hidden="true" autoComplete="off" name="website" value={form.data.website} onChange={e => form.setData('website', e.target.value)} />
            <Turnstile siteKey={turnstileSiteKey} resetKey={resetKey} onToken={v => form.setData('captcha_token', v)} />
            <div role="alert" className="text-sm text-red-700">{Object.values(form.errors).map((e, i) => <p key={i}>{e}</p>)}</div>
            <button disabled={form.processing || !form.data.captcha_token} className="rounded-control bg-teal-text px-5 py-3 font-semibold text-white disabled:opacity-50">Kirim penilaian</button>
        </form>}
    </div></PublicLayout>;
}
