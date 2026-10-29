<?php

namespace App\Http\Controllers;

use App\Services\PublicSite\PublicAnnouncements;
use App\Services\PublicSite\PublicDutyRoster;
use App\Services\PublicSite\PublicStatus;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Beranda publik (SPEC §9.2). Prop berupa closure agar partial reload (usePoll) hanya
     * menghitung bagian yang diminta.
     */
    public function __invoke(PublicStatus $status): Response
    {
        return Inertia::render('public/Home', [
            'announcements' => fn (): array => app(PublicAnnouncements::class)->active(),
            'summary' => fn (): array => $status->summary(),
            'labsNow' => fn (): array => $status->labsNow(),
            'week' => fn (): array => $status->weekSchedule(),
            'dutyWeek' => fn (): array => app(PublicDutyRoster::class)->currentWeek(),
            'networks' => fn (): array => $status->networks(),
            'apps' => fn (): array => $status->apps(),
            'display' => fn (): array => $status->settings(),
        ]);
    }
}
