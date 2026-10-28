<?php

namespace App\Http\Requests;

use App\Enums\TicketCategory;
use App\Rules\Turnstile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reporter_name' => ['required', 'string', 'max:255'],
            'reporter_contact' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf', 'max:2048'],
            'website' => ['prohibited'],
            'captcha_token' => ['required', new Turnstile($this->ip())],
        ];
    }
}
