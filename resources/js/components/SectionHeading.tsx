import type { ReactNode } from 'react';

interface SectionHeadingProps {
    id?: string;
    eyebrow: string;
    title: string;
    description?: ReactNode;
    aside?: ReactNode;
}

/** Judul section: label eyebrow mono + judul 28px/700 (SPEC §11.2). */
export default function SectionHeading({ id, eyebrow, title, description, aside }: SectionHeadingProps) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p className="eyebrow">{eyebrow}</p>
                <h2 id={id} className="mt-2 text-2xl leading-tight font-bold tracking-tight sm:text-[28px]">
                    {title}
                </h2>
                {description && <p className="mt-2 max-w-2xl text-sm text-muted">{description}</p>}
            </div>
            {aside}
        </div>
    );
}
