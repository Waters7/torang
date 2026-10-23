<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Bookings\LabAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/public/availability?date=&start=&end= — ketersediaan tiap lab untuk form pengajuan.
 */
class LabAvailabilityController extends Controller
{
    public function __invoke(Request $request, LabAvailability $availability): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['nullable', 'date_format:H:i', 'required_with:end'],
            'end' => ['nullable', 'date_format:H:i', 'required_with:start', 'after:start'],
        ]);

        return response()->json([
            'date' => $validated['date'],
            'start' => $validated['start'] ?? null,
            'end' => $validated['end'] ?? null,
            'labs' => $availability->forSlot($validated['date'], $validated['start'] ?? null, $validated['end'] ?? null)
                ->map(fn (array $lab): array => [
                    'labId' => $lab['lab_id'],
                    'status' => $lab['status'],
                    'busy' => $lab['busy'],
                ])
                ->values(),
        ]);
    }
}
