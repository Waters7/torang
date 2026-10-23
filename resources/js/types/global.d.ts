import type { SiteProps } from './public';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            site: SiteProps;
        };
    }
}
