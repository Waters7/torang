<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicSite\PublicStatus;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET /status — status layanan lengkap: riwayat 90 hari dan insiden 30 hari terakhir.
 */
class StatusPageController extends Controller
{
    public function __invoke(PublicStatus $status): Response
    {
        return Inertia::render('public/StatusPage', [
            'summary' => fn (): array => $status->summary(),
            'status' => fn (): array => $status->statusPage(),
            'display' => fn (): array => $status->settings(),
        ]);
    }
}
