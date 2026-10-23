<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PublicBookingResource;
use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingStatusController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $code = strtoupper(trim((string) $request->query('kode')));
        $booking = null;

        if ($code !== '') {
            $booking = Booking::query()->with(['lab', 'department'])->where('code', $code)->first();
        }

        return Inertia::render('public/BookingStatus', [
            'query' => $code !== '' ? $code : null,
            'booking' => $booking ? PublicBookingResource::make($booking)->resolve() : null,
        ]);
    }
}
