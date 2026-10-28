import { useState, type FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import PublicLayout from '@/layouts/PublicLayout';
import Turnstile from '@/components/Turnstile';
import { inputClass } from '@/components/form/Field';

interface Props { departments: { id: number; name: string }[]; categories: { value: string; label: string }[]; turnstileSiteKey: string }
export default function TicketCreate({ departments, categories, turnstileSiteKey }: Props) {
    const [resetKey, setResetKey] = useState(0);
    const form = useForm({ reporter_name: '', reporter_contact: '', department_id: '', category: '', title: '', description: '', attachment: null as File | null, website: '', captcha_token: '' });
    const submit = (e: FormEvent) => { e.preventDefault(); form.post('/lapor', { onError: () => setResetKey(k => k + 1) }); };
    return <PublicLayout><Head title="Lapor gangguan" />
        <div className="mx-auto max-w-5xl px-4 py-12">
            <p className="eyebrow">Helpdesk UPT TIK</p><h1 className="mt-3 text-3xl font-bold">Lapor gangguan</h1>
            <p className="mt-3 text-muted">Sampaikan kendala layanan IT. Simpan kode tiket untuk memantau penanganannya.</p>
            <div className="mt-8 grid gap-8 lg:grid-cols-[2fr_1fr]">
                <form onSubmit={submit} className="min-w-0 space-y-5 rounded-card border border-border bg-white p-5 sm:p-7">
                    <div className="grid min-w-0 gap-5 [grid-template-columns:minmax(0,1fr)] sm:grid-cols-2">
                        <label className="block text-sm font-semibold">Nama pelapor<input className={`${inputClass} mt-2`} required maxLength={255} value={form.data.reporter_name} onChange={e => form.setData('reporter_name', e.target.value)} /></label>
                        <label className="block text-sm font-semibold">Email / WhatsApp<input className={`${inputClass} mt-2`} required maxLength={255} value={form.data.reporter_contact} onChange={e => form.setData('reporter_contact', e.target.value)} /></label>
                        <label className="block text-sm font-semibold">Jurusan / unit<select className={`${inputClass} mt-2`} value={form.data.department_id} onChange={e => form.setData('department_id', e.target.value)}><option value="">Pilih (opsional)</option>{departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}</select></label>
                        <label className="block text-sm font-semibold">Kategori<select className={`${inputClass} mt-2`} required value={form.data.category} onChange={e => form.setData('category', e.target.value)}><option value="">Pilih kategori</option>{categories.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}</select></label>
                    </div>
                    <label className="block text-sm font-semibold">Judul gangguan<input className={`${inputClass} mt-2`} required maxLength={255} value={form.data.title} onChange={e => form.setData('title', e.target.value)} /></label>
                    <label className="block text-sm font-semibold">Uraian<textarea className={`${inputClass} mt-2`} required rows={5} maxLength={10000} placeholder="Jelaskan lokasi, waktu kejadian, dan kendala yang dialami." value={form.data.description} onChange={e => form.setData('description', e.target.value)} /></label>
                    <label className="block text-sm font-semibold">Lampiran (PDF, maksimal 2 MB)<input type="file" className={`${inputClass} mt-2`} accept="application/pdf" onChange={e => form.setData('attachment', e.target.files?.[0] ?? null)} /></label>
                    <div className="hidden" aria-hidden="true"><input tabIndex={-1} autoComplete="off" name="website" value={form.data.website} onChange={e => form.setData('website', e.target.value)} /></div>
                    <Turnstile siteKey={turnstileSiteKey} resetKey={resetKey} onToken={token => form.setData('captcha_token', token)} />
                    {Object.values(form.errors).length > 0 && <ul role="alert" className="space-y-1 text-sm text-red-700">{Object.entries(form.errors).map(([k, v]) => <li key={k}>{v}</li>)}</ul>}
                    {form.progress && <p className="text-sm">Mengunggah {form.progress.percentage}%</p>}
                    <button disabled={form.processing || !form.data.captcha_token} className="min-h-11 w-full rounded-control bg-teal-text px-5 font-semibold text-white disabled:opacity-50">{form.processing ? 'Mengirim…' : 'Kirim laporan'}</button>
                </form>
                <aside className="h-fit rounded-card bg-ink-dark p-6 text-white"><h2 className="text-lg font-semibold">Sudah melapor?</h2><p className="mt-3 text-sm leading-relaxed text-white/75">Periksa perkembangan dengan kode tiket. Jangan mencantumkan password akun pada laporan.</p><Link href="/cek-tiket" className="mt-6 inline-block rounded-control bg-white px-4 py-3 font-semibold text-teal-text">Cek status tiket →</Link></aside>
            </div>
        </div>
    </PublicLayout>;
}
