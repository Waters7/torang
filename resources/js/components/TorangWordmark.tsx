import TorangMark from '@/components/TorangMark';

interface TorangWordmarkProps {
    onDark?: boolean;
}

/** Wordmark ringkas untuk header/footer: simbol + "TORANG" + nama instansi (tanpa kepanjangan). */
export default function TorangWordmark({ onDark = false }: TorangWordmarkProps) {
    return (
        <span className="flex items-center gap-2.5">
            <TorangMark faint={false} className="size-9 shrink-0" />
            <span className="flex flex-col gap-1 leading-none">
                <span className={`text-lg font-extrabold tracking-[0.12em] ${onDark ? 'text-white' : 'text-ink'}`}>TORANG</span>
                <span className={`font-mono text-[10px] font-medium tracking-[0.08em] ${onDark ? 'text-teal' : 'text-teal-text'}`}>
                    POLTEKKES KEMENKES MANADO
                </span>
            </span>
        </span>
    );
}
