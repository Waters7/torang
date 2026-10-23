<?php

namespace App\Http\Controllers\Public;

use App\Enums\LabStatus;
use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Services\PublicSite\PublicStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    /**
     * GET /jadwal — kalender bulanan publik (pengajuan disetujui + maintenance).
     */
    public function show(): Response
    {
        return Inertia::render('public/Schedule', [
            'labs' => Lab::query()
                ->where('status', '!=', LabStatus::Inactive)
                ->ordered()
                ->get(['id', 'name', 'color'])
                ->toArray(),
        ]);
    }

    /**
     * GET /api/public/events?start=&end= — event kalender, maksimal 62 hari per permintaan.
     */
    public function events(Request $request, PublicStatus $status): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);

        $start = CarbonImmutable::parse($validated['start'])->setTimezone(config('app.timezone'))->startOfDay();
        $end = CarbonImmutable::parse($validated['end'])->setTimezone(config('app.timezone'))->startOfDay();

        abort_if($start->diffInDays($end) > 62, 422, 'Rentang tanggal terlalu panjang.');

        return response()->json($status->events($start, $end));
    }
}
