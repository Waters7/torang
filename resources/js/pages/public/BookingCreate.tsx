import Field, { inputClass } from '@/components/form/Field';
import StatusPill from '@/components/StatusPill';
import Turnstile from '@/components/Turnstile';
import PublicLayout from '@/layouts/PublicLayout';
import type { BookingRulesProps, LabSlotAvailability, Option, PublicLab } from '@/types/public';
import { ArrowRightIcon, DocumentArrowUpIcon, MagnifyingGlassIcon } from '@heroicons/react/24/outline';
import { Head, router, useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode, useEffect, useMemo, useState } from 'react';

interface Props {
    labs: PublicLab[];
    departments: { id: number; name: string }[];
    activityTypes: Option[];
    rules: BookingRulesProps;
    turnstileSiteKey: string | null;
    selectedLabId: number | null;
}

interface BookingForm {
    applicant_name: string;
    applicant_identity_number: string;
    applicant_email: string;
    applicant_phone: string;
    department_id: string;
    activity_name: string;
    activity_type: string;
    date: string;
    start_time: string;
    end_time: string;
    participants_count: string;
    extra_needs: string;
    lab_id: string;
    attachment: File | null;
    rules_accepted: boolean;
    website: string;
    turnstile_token: string;
}

type LabState = { tone: 'available' | 'slow' | 'offline' | 'nodata'; label: string; disabled: boolean };

function FormCard({ number, title, children }: { number: number; title: string; children: ReactNode }) {
    return (
        <section className="rounded-card border border-border bg-white p-5 sm:p-6" aria-labelledby={`step-${number}`}>
            <h2 id={`step-${number}`} className="flex items-center gap-3 text-lg font-semibold text-ink">
                <span className="grid size-8 place-items-center rounded-full bg-faint font-mono text-sm font-semibold text-teal-text">{number}</span>
                {title}
            </h2>
            <div className="mt-5">{children}</div>
        </section>
    );
}

export default function BookingCreate({ labs, departments, activityTypes, rules, turnstileSiteKey, selectedLabId }: Props) {
    const form = useForm<BookingForm>({
        applicant_name: '',
        applicant_identity_number: '',
        applicant_email: '',
        applicant_phone: '',
        department_id: '',
        activity_name: '',
        activity_type: '',
        date: '',
        start_time: '',
        end_time: '',
        participants_count: '',
        extra_needs: '',
        lab_id: selectedLabId ? String(selectedLabId) : '',
        attachment: null,
        rules_accepted: false,
        website: '',
        turnstile_token: '',
    });
    const { data, setData, errors } = form;

    const [availability, setAvailability] = useState<Record<number, LabSlotAvailability>>({});
    const [checking, setChecking] = useState(false);
    const [checkFailed, setCheckFailed] = useState(false);
    const [captchaReset, setCaptchaReset] = useState(0);
    const [statusCode, setStatusCode] = useState('');

    const hasSlot = data.start_time !== '' && data.end_time !== '' && data.end_time > data.start_time;

    // Cek ketersediaan lab secara live (debounce); validasi final tetap di server.
    useEffect(() => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(data.date)) {
            setAvailability({});
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            const params = new URLSearchParams({ date: data.date });

            if (hasSlot) {
                params.set('start', data.start_time);
                params.set('end', data.end_time);
            }

            setChecking(true);
            setCheckFailed(false);
            fetch(`/api/public/availability?${params.toString()}`, { headers: { Accept: 'application/json' }, signal: controller.signal })
                .then((response) => (response.ok ? response.json() : Promise.reject(response)))
                .then((json: { labs: LabSlotAvailability[] }) => {
                    setAvailability(Object.fromEntries(json.labs.map((lab) => [lab.labId, lab])));
                })
                .catch(() => {
                    if (!controller.signal.aborted) {
                        setAvailability({});
                        setCheckFailed(true);
                    }
                })
                .finally(() => {
                    if (!controller.signal.aborted) {
                        setChecking(false);
                    }
                });
        }, 400);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [data.date, data.start_time, data.end_time, hasSlot]);

    const labStates = useMemo(() => {
        const participants = Number(data.participants_count) || 0;

        return Object.fromEntries(
            labs.map((lab): [number, LabState] => {
                if (lab.status.value !== 'active') {
                    return [lab.id, { tone: 'slow', label: lab.status.label, disabled: true }];
                }

                const slot = availability[lab.id];

                if (hasSlot && slot && slot.busy.length > 0) {
                    const ranges = slot.busy.map((range) => `${range.start}–${range.end}`).join(', ');

                    return [lab.id, { tone: 'offline', label: `Terpakai ${ranges}`, disabled: true }];
                }

                if (participants > lab.readyPcs) {
                    return [lab.id, { tone: 'offline', label: `Hanya ${lab.readyPcs} PC siap`, disabled: true }];
                }

                if (!hasSlot || !slot) {
                    return [lab.id, { tone: 'nodata', label: 'Pilih tanggal & jam', disabled: false }];
                }

                return [lab.id, { tone: 'available', label: 'Tersedia', disabled: false }];
            }),
        );
    }, [labs, availability, hasSlot, data.participants_count]);

    // Lepas pilihan lab yang ternyata tidak bisa dipakai pada slot terpilih.
    useEffect(() => {
        if (data.lab_id && labStates[Number(data.lab_id)]?.disabled) {
            setData('lab_id', '');
        }
    }, [labStates, data.lab_id, setData]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post('/ajukan', {
            forceFormData: true,
            preserveScroll: true,
            onError: () => {
                // Token captcha hanya sekali pakai.
                setCaptchaReset((value) => value + 1);
                window.setTimeout(() => document.querySelector('[aria-invalid="true"], [role="alert"]')?.scrollIntoView({ block: 'center', behavior: 'smooth' }), 50);
            },
        });
    };

    const invalid = (field: keyof BookingForm) => (errors[field] ? true : undefined);
    const describedBy = (field: keyof BookingForm) => (errors[field] ? `${field}-error` : undefined);

    return (
        <PublicLayout>
            <Head title="Ajukan Pemakaian Lab CBT" />

            <div className="mx-auto max-w-6xl px-4 py-10 sm:py-14">
                <p className="eyebrow">Lab CBT · Pengajuan pemakaian</p>
                <h1 className="mt-3 text-[28px] leading-tight font-bold tracking-tight">Ajukan pemakaian Lab CBT</h1>
                <p className="mt-3 max-w-2xl text-muted">
                    Isi data kegiatan, pilih lab yang tersedia, lalu unggah surat permohonan. Kode pengajuan dikirim ke email Anda untuk mengecek status.
                </p>

                <div className="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <form onSubmit={submit} className="space-y-6" noValidate>
                        <FormCard number={1} title="Data pemohon">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field id="applicant_name" label="Nama lengkap" error={errors.applicant_name} required>
                                    <input id="applicant_name" className={inputClass} value={data.applicant_name} onChange={(e) => setData('applicant_name', e.target.value)} autoComplete="name" aria-invalid={invalid('applicant_name')} aria-describedby={describedBy('applicant_name')} />
                                </Field>
                                <Field id="applicant_identity_number" label="NIP / NIDN / NIM" error={errors.applicant_identity_number} required>
                                    <input id="applicant_identity_number" className={`${inputClass} font-mono`} value={data.applicant_identity_number} onChange={(e) => setData('applicant_identity_number', e.target.value)} inputMode="numeric" aria-invalid={invalid('applicant_identity_number')} aria-describedby={describedBy('applicant_identity_number')} />
                                </Field>
                                <Field id="applicant_email" label="Email" error={errors.applicant_email} hint="Kode & status pengajuan dikirim ke email ini." required>
                                    <input id="applicant_email" type="email" className={inputClass} value={data.applicant_email} onChange={(e) => setData('applicant_email', e.target.value)} autoComplete="email" aria-invalid={invalid('applicant_email')} aria-describedby={describedBy('applicant_email')} />
                                </Field>
                                <Field id="applicant_phone" label="No. WhatsApp" error={errors.applicant_phone} required>
                                    <input id="applicant_phone" type="tel" className={`${inputClass} font-mono`} value={data.applicant_phone} onChange={(e) => setData('applicant_phone', e.target.value)} placeholder="08xxxxxxxxxx" autoComplete="tel" inputMode="tel" aria-invalid={invalid('applicant_phone')} aria-describedby={describedBy('applicant_phone')} />
                                </Field>
                                <Field id="department_id" label="Unit / jurusan penyelenggara" error={errors.department_id} className="sm:col-span-2" required>
                                    <select id="department_id" className={inputClass} value={data.department_id} onChange={(e) => setData('department_id', e.target.value)} aria-invalid={invalid('department_id')} aria-describedby={describedBy('department_id')}>
                                        <option value="">Pilih unit/jurusan</option>
                                        {departments.map((department) => (
                                            <option key={department.id} value={department.id}>
                                                {department.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            </div>
                        </FormCard>

                        <FormCard number={2} title="Detail kegiatan">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field id="activity_name" label="Nama kegiatan" error={errors.activity_name} className="sm:col-span-2" required>
                                    <input id="activity_name" className={inputClass} value={data.activity_name} onChange={(e) => setData('activity_name', e.target.value)} placeholder="Ujian Akhir Semester Gizi" aria-invalid={invalid('activity_name')} aria-describedby={describedBy('activity_name')} />
                                </Field>
                                <Field id="activity_type" label="Jenis kegiatan" error={errors.activity_type} required>
                                    <select id="activity_type" className={inputClass} value={data.activity_type} onChange={(e) => setData('activity_type', e.target.value)} aria-invalid={invalid('activity_type')} aria-describedby={describedBy('activity_type')}>
                                        <option value="">Pilih jenis</option>
                                        {activityTypes.map((type) => (
                                            <option key={type.value} value={type.value}>
                                                {type.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field id="participants_count" label="Jumlah peserta" error={errors.participants_count} required>
                                    <input id="participants_count" type="number" min={1} className={`${inputClass} font-mono`} value={data.participants_count} onChange={(e) => setData('participants_count', e.target.value)} aria-invalid={invalid('participants_count')} aria-describedby={describedBy('participants_count')} />
                                </Field>
                                <Field id="date" label="Tanggal" error={errors.date} hint={`Paling lambat H-${rules.minDaysAhead} sebelum kegiatan.`} required>
                                    <input id="date" type="date" min={rules.minDate} className={`${inputClass} font-mono`} value={data.date} onChange={(e) => setData('date', e.target.value)} aria-invalid={invalid('date')} aria-describedby={describedBy('date')} />
                                </Field>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field id="start_time" label="Jam mulai" error={errors.start_time} required>
                                        <input id="start_time" type="time" min={rules.openTime} max={rules.closeTime} step={900} className={`${inputClass} font-mono`} value={data.start_time} onChange={(e) => setData('start_time', e.target.value)} aria-invalid={invalid('start_time')} aria-describedby={describedBy('start_time')} />
                                    </Field>
                                    <Field id="end_time" label="Jam selesai" error={errors.end_time} required>
                                        <input id="end_time" type="time" min={rules.openTime} max={rules.closeTime} step={900} className={`${inputClass} font-mono`} value={data.end_time} onChange={(e) => setData('end_time', e.target.value)} aria-invalid={invalid('end_time')} aria-describedby={describedBy('end_time')} />
                                    </Field>
                                </div>
                                <p className="text-xs text-muted-soft sm:col-span-2">
                                    Jam operasional {rules.openTime}–{rules.closeTime} WITA.
                                </p>
                                <Field id="extra_needs" label="Kebutuhan tambahan" error={errors.extra_needs} hint="Opsional, mis. headset, aplikasi ujian khusus, atau proyektor." className="sm:col-span-2">
                                    <textarea id="extra_needs" rows={3} className={`${inputClass} py-2.5`} value={data.extra_needs} onChange={(e) => setData('extra_needs', e.target.value)} aria-invalid={invalid('extra_needs')} />
                                </Field>
                            </div>
                        </FormCard>

                        <FormCard number={3} title="Pilih lab">
                            <fieldset aria-describedby={errors.lab_id ? 'lab_id-error' : undefined}>
                                <legend className="sr-only">Lab CBT</legend>
                                <p className="mb-4 text-sm text-muted" aria-live="polite">
                                    {checking
                                        ? 'Mengecek ketersediaan…'
                                        : checkFailed
                                          ? 'Ketersediaan lab gagal dicek. Anda tetap bisa memilih lab; jadwal diverifikasi saat pengajuan dikirim.'
                                          : hasSlot
                                            ? `Ketersediaan untuk ${data.start_time}–${data.end_time} WITA.`
                                            : 'Isi tanggal dan jam untuk melihat ketersediaan.'}
                                </p>
                                <div className="grid gap-3 sm:grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
                                    {labs.map((lab) => {
                                        const state = labStates[lab.id];

                                        return (
                                            <label key={lab.id} className={`relative block ${state.disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'}`}>
                                                <input type="radio" name="lab_id" value={lab.id} checked={data.lab_id === String(lab.id)} onChange={() => setData('lab_id', String(lab.id))} disabled={state.disabled} className="peer sr-only" />
                                                <span className="block h-full rounded-card border-2 border-border bg-white p-4 transition peer-checked:border-teal peer-checked:bg-faint/60 peer-focus-visible:ring-2 peer-focus-visible:ring-teal/40">
                                                    <span className="flex items-start justify-between gap-2">
                                                        <span>
                                                            <span className="block font-semibold text-ink">{lab.name}</span>
                                                            {lab.location && <span className="mt-0.5 block text-xs text-muted-soft">{lab.location}</span>}
                                                        </span>
                                                        <span className="mt-1 size-3 shrink-0 rounded-full" style={{ backgroundColor: lab.color }} aria-hidden="true" />
                                                    </span>
                                                    <span className="mt-3 flex gap-4 font-mono text-xs text-muted">
                                                        <span>{lab.capacity} kursi</span>
                                                        <span>{lab.readyPcs} PC siap</span>
                                                    </span>
                                                    <StatusPill tone={state.tone} className="mt-3">
                                                        {state.label}
                                                    </StatusPill>
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                                {errors.lab_id && (
                                    <p id="lab_id-error" className="mt-3 text-sm text-offline-text" role="alert">
                                        {errors.lab_id}
                                    </p>
                                )}
                            </fieldset>
                        </FormCard>

                        <FormCard number={4} title="Lampiran & persetujuan">
                            <div className="space-y-5">
                                <Field id="attachment" label="Surat permohonan (PDF, maks. 2 MB)" error={errors.attachment} required>
                                    <label htmlFor="attachment" className="flex min-h-11 cursor-pointer items-center gap-3 rounded-control border border-dashed border-border bg-surface px-3 py-3 text-sm text-muted hover:border-teal">
                                        <DocumentArrowUpIcon className="size-6 shrink-0 text-teal-text" aria-hidden="true" />
                                        <span className="truncate">{data.attachment ? data.attachment.name : 'Pilih file PDF surat permohonan'}</span>
                                    </label>
                                    <input id="attachment" type="file" accept="application/pdf,.pdf" className="sr-only" onChange={(e) => setData('attachment', e.target.files?.[0] ?? null)} aria-invalid={invalid('attachment')} aria-describedby={describedBy('attachment')} />
                                </Field>

                                {form.progress?.percentage !== undefined && (
                                    <div className="h-2 overflow-hidden rounded-full bg-surface" role="progressbar" aria-valuenow={form.progress.percentage} aria-valuemin={0} aria-valuemax={100} aria-label="Mengunggah lampiran">
                                        <div className="h-full rounded-full bg-teal transition-all" style={{ width: `${form.progress.percentage}%` }} />
                                    </div>
                                )}

                                <div>
                                    <label className="flex cursor-pointer items-start gap-3 text-sm text-ink">
                                        <input type="checkbox" checked={data.rules_accepted} onChange={(e) => setData('rules_accepted', e.target.checked)} className="mt-0.5 size-5 shrink-0 rounded border-border accent-teal-text" aria-invalid={invalid('rules_accepted')} />
                                        <span>Saya telah membaca dan menyetujui tata tertib penggunaan Lab CBT, termasuk menjaga perangkat dan kebersihan ruangan.</span>
                                    </label>
                                    {errors.rules_accepted && (
                                        <p className="mt-1.5 text-sm text-offline-text" role="alert">
                                            {errors.rules_accepted}
                                        </p>
                                    )}
                                </div>

                                {/* Honeypot: tidak terlihat oleh manusia. */}
                                <div className="absolute -left-[9999px]" aria-hidden="true">
                                    <label htmlFor="website">Website</label>
                                    <input id="website" tabIndex={-1} autoComplete="off" value={data.website} onChange={(e) => setData('website', e.target.value)} />
                                </div>

                                <div>
                                    {turnstileSiteKey ? (
                                        <Turnstile siteKey={turnstileSiteKey} resetKey={captchaReset} onToken={(token) => setData('turnstile_token', token)} />
                                    ) : (
                                        <p className="rounded-control bg-slow-bg p-3 text-sm text-slow-text">Captcha belum dikonfigurasi. Hubungi helpdesk UPT TIK.</p>
                                    )}
                                    {errors.turnstile_token && (
                                        <p className="mt-1.5 text-sm text-offline-text" role="alert">
                                            {errors.turnstile_token}
                                        </p>
                                    )}
                                    {errors.website && (
                                        <p className="mt-1.5 text-sm text-offline-text" role="alert">
                                            {errors.website}
                                        </p>
                                    )}
                                </div>

                                <button type="submit" disabled={form.processing || !turnstileSiteKey} className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-control bg-teal-text px-5 text-sm font-semibold text-white hover:bg-ink disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                                    {form.processing ? 'Mengirim…' : 'Kirim Pengajuan'}
                                    {!form.processing && <ArrowRightIcon className="size-4" aria-hidden="true" />}
                                </button>
                            </div>
                        </FormCard>
                    </form>

                    <aside className="space-y-6 lg:sticky lg:top-6 lg:self-start">
                        <div className="rounded-card bg-ink-dark p-6 text-white">
                            <h2 className="font-mono text-xs font-semibold tracking-[0.14em] text-teal uppercase">Ketentuan</h2>
                            <ol className="mt-4 space-y-4 text-sm leading-relaxed text-white/85">
                                {[
                                    `Pengajuan paling lambat H-${rules.minDaysAhead} sebelum tanggal kegiatan.`,
                                    'Jumlah peserta tidak melebihi PC siap pakai di lab yang dipilih.',
                                    'Lampirkan surat permohonan resmi dari unit/jurusan dalam format PDF.',
                                    'Kegiatan didampingi petugas IT sesuai jadwal jaga CBT Center.',
                                ].map((item, index) => (
                                    <li key={item} className="flex gap-3">
                                        <span className="font-mono text-sm font-semibold text-lime">{String(index + 1).padStart(2, '0')}</span>
                                        <span>{item}</span>
                                    </li>
                                ))}
                            </ol>
                        </div>

                        <form
                            className="rounded-card border border-border bg-white p-6"
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.get('/cek-status', { kode: statusCode });
                            }}
                        >
                            <h2 className="font-semibold text-ink">Cek status pengajuan</h2>
                            <p className="mt-1 text-sm text-muted">Masukkan kode yang dikirim ke email Anda.</p>
                            <label htmlFor="status-code" className="sr-only">
                                Kode pengajuan
                            </label>
                            <div className="mt-4 flex gap-2">
                                <input id="status-code" className={`${inputClass} font-mono uppercase`} placeholder="CBT-2610-XXXX" value={statusCode} onChange={(e) => setStatusCode(e.target.value)} />
                                <button type="submit" className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-control border border-teal-text text-teal-text hover:bg-faint" aria-label="Cek status">
                                    <MagnifyingGlassIcon className="size-5" aria-hidden="true" />
                                </button>
                            </div>
                        </form>

                        <a href="/jadwal" className="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-teal-text hover:text-ink">
                            Lihat jadwal Lab CBT
                            <ArrowRightIcon className="size-4" aria-hidden="true" />
                        </a>
                    </aside>
                </div>
            </div>
        </PublicLayout>
    );
}
