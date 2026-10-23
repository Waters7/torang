import type { ReactNode } from 'react';

export const inputClass =
    'block min-h-11 w-full rounded-control border border-border bg-white px-3 text-sm text-ink placeholder:text-muted-soft/70 focus:border-teal focus:ring-2 focus:ring-teal/25 focus:outline-none disabled:bg-surface aria-[invalid=true]:border-offline';

interface FieldProps {
    id: string;
    label: string;
    error?: string;
    hint?: ReactNode;
    required?: boolean;
    className?: string;
    children: ReactNode;
}

/** Label + kontrol + pesan error/hint untuk form publik. */
export default function Field({ id, label, error, hint, required = false, className = '', children }: FieldProps) {
    return (
        <div className={className}>
            <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-ink">
                {label}
                {required && (
                    <span className="text-offline-text" aria-hidden="true">
                        {' '}
                        *
                    </span>
                )}
            </label>
            {children}
            {error ? (
                <p id={`${id}-error`} className="mt-1.5 text-sm text-offline-text" role="alert">
                    {error}
                </p>
            ) : (
                hint && <p className="mt-1.5 text-xs text-muted-soft">{hint}</p>
            )}
        </div>
    );
}
