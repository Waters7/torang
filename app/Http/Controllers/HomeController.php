<?php

namespace App\Http\Controllers;

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
            'summary' => fn (): array => $status->summary(),
            'labsNow' => fn (): array => $status->labsNow(),
            'week' => fn (): array => $status->weekSchedule(),
            'networks' => fn (): array => $status->networks(),
            'apps' => fn (): array => $status->apps(),
            'display' => fn (): array => $status->settings(),
        ]);
    }
}
