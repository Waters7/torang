<?php

namespace App\Http\Controllers\Public;

use App\Enums\TicketCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Resources\Public\PublicTicketResource;
use App\Models\Department;
use App\Models\Ticket;
use App\Rules\Turnstile;
use App\Services\Helpdesk\TicketService;
use App\Services\TurnstileVerifier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function create(TurnstileVerifier $turnstile)
    {
        return Inertia::render('public/TicketCreate', [
            'departments' => Department::ordered()->get(['id', 'name'])->toArray(),
            'categories' => collect(TicketCategory::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()])->all(),
            'turnstileSiteKey' => $turnstile->siteKey(),
        ]);
    }

    public function store(StoreTicketRequest $request, TicketService $service)
    {
        $result = $service->create($request->validated(), attachment: $request->file('attachment'));

        return to_route('tickets.sent', $result['ticket']->code)->with('ticket_rating_url', route('tickets.rating', [$result['ticket']->code, $result['token']]));
    }

    public function sent(Request $request, string $code)
    {
        $ticket = Ticket::where('code', $code)->firstOrFail();

        return Inertia::render('public/TicketStatus', ['query' => $code, 'ticket' => PublicTicketResource::make($ticket)->resolve(),
            'ratingUrl' => $request->session()->get('ticket_rating_url'), 'sent' => true])->toResponse($request)->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function status(Request $request)
    {
        $data = $request->validate(['kode' => ['nullable', 'string', 'max:40']]);
        $query = strtoupper(trim((string) ($data['kode'] ?? '')));
        $ticket = $query ? Ticket::where('code', $query)->first() : null;

        return Inertia::render('public/TicketStatus', ['query' => $query, 'ticket' => $ticket ? PublicTicketResource::make($ticket)->resolve() : null, 'ratingUrl' => null, 'sent' => false]);
    }

    public function rating(Request $request, string $code, string $token, TicketService $service, TurnstileVerifier $turnstile)
    {
        $ticket = Ticket::where('code', $code)->firstOrFail();
        abort_unless($service->validToken($ticket, $token), 404);

        return Inertia::render('public/TicketRating', ['ticket' => PublicTicketResource::make($ticket)->resolve(),
            'rated' => $ticket->rated_at !== null, 'submitUrl' => route('tickets.rate', [$code, $token]),
            'comments' => $ticket->comments()->where('is_internal', false)->orderBy('created_at')->get()->map(fn ($comment) => ['body' => $comment->body, 'at' => $comment->created_at->toIso8601String()])->all(),
            'turnstileSiteKey' => $turnstile->siteKey()])->toResponse($request)->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function rate(Request $request, string $code, string $token, TicketService $service)
    {
        $request->validate(['website' => ['prohibited'], 'captcha_token' => ['required', new Turnstile($request->ip())]]);
        $service->rate(Ticket::where('code', $code)->firstOrFail(), $token, $request->only('rating', 'feedback'));

        return to_route('tickets.rating', [$code, $token]);
    }
}
