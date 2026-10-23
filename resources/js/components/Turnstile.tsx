import { useEffect, useRef } from 'react';

interface TurnstileApi {
    render: (container: HTMLElement, options: Record<string, unknown>) => string;
    reset: (widgetId: string) => void;
    remove: (widgetId: string) => void;
}

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
let scriptPromise: Promise<TurnstileApi> | null = null;

function loadTurnstile(): Promise<TurnstileApi> {
    if (window.turnstile) {
        return Promise.resolve(window.turnstile);
    }

    scriptPromise ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT_URL;
        script.async = true;
        script.defer = true;
        script.onload = () => (window.turnstile ? resolve(window.turnstile) : reject(new Error('Turnstile tidak tersedia')));
        script.onerror = () => {
            scriptPromise = null;
            reject(new Error('Gagal memuat captcha'));
        };
        document.head.appendChild(script);
    });

    return scriptPromise;
}

interface TurnstileProps {
    siteKey: string;
    onToken: (token: string) => void;
    /** Ubah nilai ini untuk meminta token baru (token hanya sekali pakai). */
    resetKey: number;
}

/** Widget Cloudflare Turnstile (mode explicit). Token diverifikasi ulang di server. */
export default function Turnstile({ siteKey, onToken, resetKey }: TurnstileProps) {
    const container = useRef<HTMLDivElement>(null);
    const widgetId = useRef<string | null>(null);
    const onTokenRef = useRef(onToken);
    onTokenRef.current = onToken;

    useEffect(() => {
        let cancelled = false;

        loadTurnstile()
            .then((turnstile) => {
                if (cancelled || !container.current) {
                    return;
                }

                widgetId.current = turnstile.render(container.current, {
                    sitekey: siteKey,
                    language: 'id',
                    callback: (token: string) => onTokenRef.current(token),
                    'expired-callback': () => onTokenRef.current(''),
                    'error-callback': () => onTokenRef.current(''),
                });
            })
            .catch(() => onTokenRef.current(''));

        return () => {
            cancelled = true;

            if (widgetId.current && window.turnstile) {
                window.turnstile.remove(widgetId.current);
                widgetId.current = null;
            }
        };
    }, [siteKey]);

    useEffect(() => {
        if (resetKey > 0 && widgetId.current && window.turnstile) {
            onTokenRef.current('');
            window.turnstile.reset(widgetId.current);
        }
    }, [resetKey]);

    return <div ref={container} className="min-h-[65px]" />;
}
