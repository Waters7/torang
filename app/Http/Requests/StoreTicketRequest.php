<?php

namespace App\Http\Requests;

use App\Rules\Turnstile;
use App\Services\Helpdesk\TicketService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form "Lapor gangguan" publik: isian laporan + lampiran PDF opsional, honeypot, dan captcha.
 */
class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...TicketService::rules(),
            'attachment' => ['nullable', 'file', 'mimes:pdf', 'max:2048'],
            'website' => ['prohibited'],
            'captcha_token' => ['required', new Turnstile($this->ip())],
        ];
    }
}
