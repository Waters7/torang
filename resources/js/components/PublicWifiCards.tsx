import type { PublicWifiNetwork } from '@/types/public';
import { CheckIcon, ClipboardDocumentIcon, WifiIcon } from '@heroicons/react/24/outline';
import { useState } from 'react';

function CopyButton({ value, label }: { value: string; label: string }) {
    const [copied, setCopied] = useState(false);

    async function copy() {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            setCopied(false);
        }
    }

    return (
        <button
            type="button"
            onClick={copy}
            className="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-control border border-border bg-white px-3 text-xs font-semibold text-teal-text hover:border-teal"
            aria-label={label}
        >
            {copied ? <CheckIcon className="size-4" aria-hidden="true" /> : <ClipboardDocumentIcon className="size-4" aria-hidden="true" />}
            {copied ? 'Disalin' : 'Salin'}
        </button>
    );
}

/** SSID & password WiFi yang dipilih petugas untuk ditampilkan di halaman depan. */
export default function PublicWifiCards({ networks }: { networks: PublicWifiNetwork[] }) {
    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {networks.map((network) => (
                <article key={network.id} className="min-w-0 rounded-card border border-border bg-white p-5">
                    <header className="flex items-start gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-control bg-faint text-teal-text">
                            <WifiIcon className="size-5" aria-hidden="true" />
                        </span>
                        <div className="min-w-0">
                            <h3 className="font-mono text-base font-semibold [overflow-wrap:anywhere]">{network.ssid}</h3>
                            <p className="mt-1 text-xs leading-5 text-muted">{network.location}</p>
                            <p className="mt-0.5 text-xs text-muted-soft">
                                {network.audience} · {network.security}
                            </p>
                        </div>
                    </header>
                    <div className="mt-4 flex items-center justify-between gap-3 rounded-control bg-faint px-3 py-2">
                        {network.password ? (
                            <>
                                <div className="min-w-0">
                                    <p className="text-[11px] tracking-wide text-muted-soft uppercase">Password</p>
                                    <p className="font-mono text-sm font-semibold break-all select-all">{network.password}</p>
                                </div>
                                <CopyButton value={network.password} label={`Salin password ${network.ssid}`} />
                            </>
                        ) : (
                            <p className="py-2 text-sm text-muted">Tanpa password</p>
                        )}
                    </div>
                </article>
            ))}
        </div>
    );
}
