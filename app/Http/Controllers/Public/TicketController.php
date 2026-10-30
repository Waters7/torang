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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Lapor gangguan publik (SPEC §9.1 /lapor): kirim laporan, cek status dengan kode tiket, dan penilaian
 * lewat tautan rahasia. Halaman tidak memuat data pelapor; halaman bertoken tidak di-cache dan tanpa Referer.
 */
class TicketController extends Controller
{
    public function create(TurnstileVerifier $turnstile): Response
    {
        return Inertia::render('public/TicketCreate', [
            'departments' => Department::query()->ordered()->get(['id', 'name'])->toArray(),
            'categories' => collect(TicketCategory::cases())
                ->map(fn (TicketCategory $category): array => ['value' => $category->value, 'label' => $category->label()])
                ->all(),
            'turnstileSiteKey' => $turnstile->siteKey(),
        ]);
    }

    public function store(StoreTicketRequest $request, TicketService $service): RedirectResponse
    {
        $result = $service->create($request->validated(), attachment: $request->file('attachment'));
        $code = $result['ticket']->code;

        return to_route('tickets.sent', $code)
            ->with('ticket_rating_url', route('tickets.rating', [$code, $result['token']]));
    }

    public function sent(Request $request, string $code): HttpResponse
    {
        $ticket = Ticket::query()->where('code', $code)->firstOrFail();

        return $this->private(Inertia::render('public/TicketStatus', [
            'query' => $code,
            'ticket' => PublicTicketResource::make($ticket)->resolve(),
            'ratingUrl' => $request->session()->get('ticket_rating_url'),
            'sent' => true,
        ]), $request);
    }

    public function status(Request $request): Response
    {
        $data = $request->validate(['kode' => ['nullable', 'string', 'max:40']]);
        $query = strtoupper(trim((string) ($data['kode'] ?? '')));
        $ticket = $query ? Ticket::query()->where('code', $query)->first() : null;

        return Inertia::render('public/TicketStatus', [
            'query' => $query,
            'ticket' => $ticket ? PublicTicketResource::make($ticket)->resolve() : null,
            'ratingUrl' => null,
            'sent' => false,
        ]);
    }

    public function rating(Request $request, string $code, string $token, TicketService $service, TurnstileVerifier $turnstile): HttpResponse
    {
        $ticket = Ticket::query()->where('code', $code)->firstOrFail();
        abort_unless($service->validToken($ticket, $token), 404);

        return $this->private(Inertia::render('public/TicketRating', [
            'ticket' => PublicTicketResource::make($ticket)->resolve(),
            'rated' => $ticket->rated_at !== null,
            'submitUrl' => route('tickets.rate', [$code, $token]),
            'comments' => $ticket->comments()
                ->where('is_internal', false)
                ->orderBy('created_at')
                ->get()
                ->map(fn ($comment): array => ['body' => $comment->body, 'at' => $comment->created_at->toIso8601String()])
                ->all(),
            'turnstileSiteKey' => $turnstile->siteKey(),
        ]), $request);
    }

    public function rate(Request $request, string $code, string $token, TicketService $service): RedirectResponse
    {
        $request->validate([
            'website' => ['prohibited'],
            'captcha_token' => ['required', new Turnstile($request->ip())],
        ]);

        $service->rate(Ticket::query()->where('code', $code)->firstOrFail(), $token, $request->only('rating', 'feedback'));

        return to_route('tickets.rating', [$code, $token]);
    }

    /**
     * Halaman bertoken/berkode: jangan di-cache dan jangan kirim URL lewat Referer.
     */
    private function private(Response $page, Request $request): HttpResponse
    {
        return $page->toResponse($request)
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
