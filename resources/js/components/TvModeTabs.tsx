import type { TvModeTab } from '@/types/public';
import { ComputerDesktopIcon, TvIcon } from '@heroicons/react/24/outline';
import { useEffect, useRef } from 'react';

export default function TvModeTabs({ tabs }: { tabs: TvModeTab[] }) {
    const navigation = useRef<HTMLElement>(null);
    const activeKey = tabs.find((tab) => tab.active)?.key;

    useEffect(() => {
        const nav = navigation.current;
        const active = nav?.querySelector<HTMLElement>('[aria-current="page"]');
        if (!nav || !active) return;

        const revealActiveTab = () => {
            nav.scrollLeft += active.getBoundingClientRect().left - nav.getBoundingClientRect().left - (nav.clientWidth - active.offsetWidth) / 2;
        };

        revealActiveTab();
        // Ukuran tab berubah setelah font dimuat atau layar diubah ukurannya.
        const observer = new ResizeObserver(revealActiveTab);
        observer.observe(nav);
        if (nav.firstElementChild) observer.observe(nav.firstElementChild);

        return () => observer.disconnect();
    }, [activeKey]);

    return (
        <nav ref={navigation} aria-label="Pilihan tampilan TV" className="min-w-0 overflow-x-auto py-3">
            <div className="flex w-max min-w-full gap-2">
                {tabs.map((tab) => {
                    const Icon = tab.key === 'it' ? TvIcon : ComputerDesktopIcon;

                    return (
                        <a
                            key={tab.key}
                            href={tab.url}
                            aria-current={tab.active ? 'page' : undefined}
                            className={`inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#70DBCD] motion-reduce:transition-none ${tab.active ? 'border-teal bg-teal text-ink-dark' : 'border-white/15 bg-white/5 text-white/75 hover:border-[#70DBCD] hover:text-white'}`}
                        >
                            <Icon className="size-4 shrink-0" aria-hidden="true" />
                            {tab.label}
                        </a>
                    );
                })}
            </div>
        </nav>
    );
}
