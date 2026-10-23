<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicSite\PublicStatus;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/public/summary — ringkasan status layanan (cache 30 detik), mis. untuk layar lain.
 */
class PublicSummaryController extends Controller
{
    public function __invoke(PublicStatus $status): JsonResponse
    {
        return response()->json($status->summary())
            ->header('Cache-Control', 'public, max-age='.PublicStatus::CACHE_SECONDS);
    }
}
