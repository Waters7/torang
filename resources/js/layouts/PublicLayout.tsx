import TorangWordmark from '@/components/TorangWordmark';
import { Bars3Icon, ChatBubbleLeftRightIcon, ClockIcon, EnvelopeIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { Link, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

const navItems = [
    { label: 'Layanan', href: '/#layanan' },
    { label: 'Jadwal CBT', href: '/#jadwal' },
    { label: 'Petugas', href: '/#petugas' },
    { label: 'Status layanan', href: '/status' },
];

const quickLinks = [
    { label: 'Jadwal Lab CBT', href: '/jadwal' },
    { label: 'Ajukan Pemakaian', href: '/ajukan' },
    { label: 'Cek Status Pengajuan', href: '/cek-status' },
    { label: 'Status Layanan', href: '/status' },
    { label: 'Lapor Gangguan', href: '/lapor' },
    { label: 'Cek Status Tiket', href: '/cek-tiket' },
];

/** Nomor lokal 08xx menjadi tautan wa.me/628xx. */
function whatsappUrl(phone: string): string {
    const digits = phone.replace(/\D/g, '');

    return `https://wa.me/${digits.startsWith('0') ? `62${digits.slice(1)}` : digits}`;
}

export default function PublicLayout({ children }: { children: ReactNode }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const { site } = usePage().props;
    const { helpdesk } = site;
    const hasHelpdesk = helpdesk.whatsapp || helpdesk.email || helpdesk.serviceHours;

    return (
        <div className="torang-public flex min-h-screen flex-col">
            <a
                href="#konten-utama"
                className="sr-only z-50 rounded-control bg-white p-4 text-teal-text focus:not-sr-only focus:absolute focus:top-2 focus:left-2"
            >
                Lewati ke konten utama
            </a>
            <header className="border-b border-border bg-white">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
                    <Link href="/" className="mr-auto rounded-control" aria-label="TORANG — Beranda">
                        <TorangWordmark />
                    </Link>

                    <button
                        type="button"
                        aria-label={menuOpen ? 'Tutup menu' : 'Buka menu'}
                        aria-expanded={menuOpen}
                        aria-controls="public-navigation"
                        onClick={() => setMenuOpen((open) => !open)}
                        className="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-control border border-border text-ink lg:hidden"
                    >
                        {menuOpen ? (
                            <XMarkIcon className="size-5" aria-hidden="true" />
                        ) : (
                            <Bars3Icon className="size-5" aria-hidden="true" />
                        )}
                    </button>

                    <nav
                        id="public-navigation"
                        aria-label="Menu utama"
                        className={`${menuOpen ? 'block' : 'hidden'} order-last w-full border-t border-border pt-2 lg:order-none lg:block lg:w-auto lg:border-0 lg:pt-0`}
                        onKeyDown={(event) => {
                            if (event.key === 'Escape') {
                                setMenuOpen(false);
                                document.querySelector<HTMLButtonElement>('[aria-controls="public-navigation"]')?.focus();
                            }
                        }}
                    >
                        <ul className="flex flex-wrap gap-x-1">
                            {navItems.map((item) => (
                                <li key={item.href}>
                                    <a
                                        href={item.href}
                                        onClick={() => setMenuOpen(false)}
                                        className="inline-flex min-h-11 items-center rounded-control px-3 text-xs font-medium whitespace-nowrap text-muted hover:bg-surface hover:text-ink"
                                    >
                                        {item.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                        <div className="mt-2 grid grid-cols-2 gap-2 pb-2 lg:hidden">
                            <Link
                                href="/ajukan"
                                onClick={() => setMenuOpen(false)}
                                className="inline-flex min-h-11 items-center justify-center rounded-control bg-teal-text px-3 text-xs font-semibold text-white"
                            >
                                Ajukan pemakaian
                            </Link>
                            <a
                                href="/admin"
                                className="inline-flex min-h-11 items-center justify-center rounded-control border border-teal-text px-3 text-xs font-semibold text-teal-text"
                            >
                                Masuk petugas
                            </a>
                        </div>
                    </nav>

                    <div className="hidden gap-2 lg:flex">
                        <Link
                            href="/ajukan"
                            className="inline-flex min-h-11 flex-1 items-center justify-center rounded-control bg-teal-text px-3 text-sm font-semibold whitespace-nowrap text-white hover:bg-ink sm:flex-none sm:px-4"
                        >
                            Ajukan Pemakaian
                        </Link>
                        <a
                            href="/admin"
                            className="inline-flex min-h-11 flex-1 items-center justify-center rounded-control border border-teal-text px-3 text-sm font-semibold whitespace-nowrap text-teal-text hover:bg-faint sm:flex-none sm:px-4"
                        >
                            Masuk Petugas
                        </a>
                    </div>
                </div>
            </header>

            <main id="konten-utama" tabIndex={-1} className="min-w-0 flex-1">
                {children}
            </main>

            <footer className="bg-ink-dark text-white/75">
                <div className="mx-auto grid max-w-6xl gap-10 px-4 py-12 md:grid-cols-[1.4fr_1fr_1fr]">
                    <div>
                        <TorangWordmark onDark />
                        <p className="mt-4 max-w-sm text-sm leading-relaxed">
                            <span className="font-semibold text-white">TORANG</span> — Tata Operasional Ruang CBT, Aplikasi, Network &amp;
                            Gangguan.
                        </p>
                        <p className="mt-2 text-sm">
                            Dikelola oleh {site.unit} {site.institution}.
                        </p>
                    </div>

                    <div>
                        <h2 className="font-mono text-xs font-semibold tracking-[0.14em] text-teal uppercase">Kontak helpdesk</h2>
                        {hasHelpdesk ? (
                            <ul className="mt-4 space-y-3 text-sm">
                                {helpdesk.whatsapp && (
                                    <li>
                                        <a
                                            href={whatsappUrl(helpdesk.whatsapp)}
                                            className="inline-flex items-center gap-2 hover:text-white"
                                        >
                                            <ChatBubbleLeftRightIcon className="size-5 shrink-0" aria-hidden="true" />
                                            WhatsApp {helpdesk.whatsapp}
                                        </a>
                                    </li>
                                )}
                                {helpdesk.email && (
                                    <li>
                                        <a href={`mailto:${helpdesk.email}`} className="inline-flex items-center gap-2 hover:text-white">
                                            <EnvelopeIcon className="size-5 shrink-0" aria-hidden="true" />
                                            {helpdesk.email}
                                        </a>
                                    </li>
                                )}
                                {helpdesk.serviceHours && (
                                    <li className="inline-flex items-center gap-2">
                                        <ClockIcon className="size-5 shrink-0" aria-hidden="true" />
                                        {helpdesk.serviceHours}
                                    </li>
                                )}
                            </ul>
                        ) : (
                            <p className="mt-4 text-sm">Kontak helpdesk belum diatur.</p>
                        )}
                    </div>

                    <div>
                        <h2 className="font-mono text-xs font-semibold tracking-[0.14em] text-teal uppercase">Tautan cepat</h2>
                        <ul className="mt-4 space-y-2 text-sm">
                            {quickLinks.map((item) => (
                                <li key={item.href}>
                                    <Link href={item.href} className="hover:text-white">
                                        {item.label}
                                    </Link>
                                </li>
                            ))}
                            <li>
                                <a href="/admin" className="hover:text-white">
                                    Masuk Petugas
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div className="border-t border-white/10">
                    <p className="mx-auto max-w-6xl px-4 py-5 font-mono text-xs tracking-[0.08em] text-white/50">
                        © {new Date().getFullYear()} {site.unit} · {site.institution.toUpperCase()}
                    </p>
                </div>
            </footer>
        </div>
    );
}
