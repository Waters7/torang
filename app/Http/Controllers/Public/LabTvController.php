<?php

namespace App\Http\Controllers\Public;

use App\Enums\LabStatus;
use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Services\PublicSite\LabTvBoard;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET /tv/lab/{kode} — layar TV di depan lab, tanpa login, diperbarui otomatis.
 */
class LabTvController extends Controller
{
    public function __invoke(Lab $lab, LabTvBoard $board): Response
    {
        abort_if($lab->status === LabStatus::Inactive, 404);

        return Inertia::render('public/LabTv', [
            'board' => fn (): array => $board->board($lab),
        ]);
    }
}
