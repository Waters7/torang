<?php

namespace App\Http\Controllers\Public;

use App\Actions\Bookings\SubmitPublicBooking;
use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\Public\PublicBookingResource;
use App\Http\Resources\Public\PublicLabResource;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Lab;
use App\Services\Bookings\BookingRules;
use App\Services\TurnstileVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function create(Request $request, BookingRules $rules, TurnstileVerifier $turnstile): Response
    {
        $labs = Lab::query()->ordered()->with('deviceCounts')->get();
        $selected = $request->query('lab');

        return Inertia::render('public/BookingCreate', [
            'labs' => PublicLabResource::collection($labs)->resolve(),
            'departments' => Department::query()->ordered()->get(['id', 'name'])->toArray(),
            'activityTypes' => collect(ActivityType::publicCases())
                ->map(fn (ActivityType $type): array => ['value' => $type->value, 'label' => $type->label()])
                ->all(),
            'rules' => [
                'minDate' => $rules->minimumPublicDate()->toDateString(),
                'minDaysAhead' => $rules->minDaysAhead(),
                'openTime' => $rules->openTime(),
                'closeTime' => $rules->closeTime(),
            ],
            'turnstileSiteKey' => $turnstile->siteKey(),
            'selectedLabId' => $labs->first(fn (Lab $lab): bool => (string) $lab->id === $selected || $lab->code === $selected)?->id,
        ]);
    }

    public function store(StoreBookingRequest $request, SubmitPublicBooking $submit): RedirectResponse
    {
        $booking = $submit->handle($request->bookingData(), $request->file('attachment'));

        return redirect()->route('bookings.sent', $booking->code);
    }

    public function sent(string $code): Response
    {
        $booking = Booking::query()->with(['lab', 'department'])->where('code', strtoupper($code))->firstOrFail();

        return Inertia::render('public/BookingSent', [
            'booking' => PublicBookingResource::make($booking)->resolve(),
        ]);
    }
}
