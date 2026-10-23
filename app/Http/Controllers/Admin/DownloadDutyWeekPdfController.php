<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DutyWeek;
use App\Services\Duty\DutyWeekPdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DownloadDutyWeekPdfController extends Controller
{
    public function __invoke(DutyWeek $dutyWeek, DutyWeekPdf $pdf): Response
    {
        Gate::authorize('downloadPdf', $dutyWeek);

        return $pdf->download($dutyWeek);
    }
}
