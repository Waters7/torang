<?php

namespace App\Http\Requests;

use App\Enums\ActivityType;
use App\Rules\Turnstile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form pengajuan publik. Aturan bentrok/kapasitas/H-3 dicek di BookingRules oleh SubmitPublicBooking.
 */
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'applicant_phone' => preg_replace('/[\s\-().]/', '', (string) $this->input('applicant_phone')),
            'applicant_email' => strtolower(trim((string) $this->input('applicant_email'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'applicant_name' => ['required', 'string', 'max:255'],
            'applicant_identity_number' => ['required', 'string', 'max:30', 'regex:/^[0-9A-Za-z.\-]+$/'],
            'applicant_email' => ['required', 'email', 'max:255'],
            'applicant_phone' => ['required', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'activity_name' => ['required', 'string', 'max:255'],
            'activity_type' => ['required', Rule::enum(ActivityType::class)->only(ActivityType::publicCases())],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'participants_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'extra_needs' => ['nullable', 'string', 'max:1000'],
            'lab_id' => ['required', 'integer', Rule::exists('labs', 'id')],
            'attachment' => ['required', 'file', 'mimes:pdf', 'max:2048'],
            'rules_accepted' => ['accepted'],
            // Honeypot: field tersembunyi, manusia tidak mengisinya.
            'website' => ['prohibited'],
            'turnstile_token' => ['required', 'string', new Turnstile($this->ip())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'applicant_name' => 'nama pemohon',
            'applicant_identity_number' => 'NIP/NIDN/NIM',
            'applicant_email' => 'email',
            'applicant_phone' => 'nomor WhatsApp',
            'department_id' => 'unit/jurusan penyelenggara',
            'activity_name' => 'nama kegiatan',
            'activity_type' => 'jenis kegiatan',
            'date' => 'tanggal',
            'start_time' => 'jam mulai',
            'end_time' => 'jam selesai',
            'participants_count' => 'jumlah peserta',
            'extra_needs' => 'kebutuhan tambahan',
            'lab_id' => 'lab',
            'attachment' => 'surat permohonan',
            'turnstile_token' => 'captcha',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'applicant_phone.regex' => 'Nomor WhatsApp tidak valid (contoh: 081234567890).',
            'applicant_identity_number.regex' => 'NIP/NIDN/NIM hanya boleh berisi huruf, angka, titik, dan tanda hubung.',
            'attachment.mimes' => 'Surat permohonan harus berupa file PDF.',
            'attachment.max' => 'Ukuran surat permohonan maksimal 2 MB.',
            'rules_accepted.accepted' => 'Anda harus menyetujui tata tertib penggunaan Lab CBT.',
            'turnstile_token.required' => 'Selesaikan captcha terlebih dahulu.',
            'website.prohibited' => 'Pengajuan tidak dapat diproses.',
        ];
    }

    /**
     * Data untuk SubmitPublicBooking (tanpa field form pendukung).
     *
     * @return array<string, mixed>
     */
    public function bookingData(): array
    {
        return $this->safe()->except(['attachment', 'rules_accepted', 'website', 'turnstile_token']);
    }
}
