<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'site' => fn (): array => $this->site(),
        ];
    }

    /**
     * Identitas & kontak helpdesk untuk header/footer publik.
     * Hanya data yang memang untuk publik, tidak ada data pribadi.
     *
     * @return array<string, mixed>
     */
    private function site(): array
    {
        $settings = app(SettingsService::class);

        return [
            'institution' => $settings->get('identity.institution_name'),
            'unit' => $settings->get('identity.unit_name'),
            'helpdesk' => [
                'whatsapp' => $settings->get('identity.helpdesk_whatsapp') ?: null,
                'email' => $settings->get('identity.helpdesk_email') ?: null,
                'serviceHours' => $settings->get('identity.service_hours') ?: null,
            ],
        ];
    }
}
