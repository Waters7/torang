<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Lab;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'CBT-'.now()->format('ym').'-'.Str::upper(Str::random(4)),
            'lab_id' => Lab::factory()->withPcs(40),
            'applicant_name' => fake()->name(),
            'applicant_identity_number' => fake()->numerify('19##############'),
            'applicant_email' => fake()->safeEmail(),
            'applicant_phone' => fake()->numerify('08##########'),
            'activity_name' => 'Ujian Akhir Semester '.fake()->randomElement(['Keperawatan', 'Gizi', 'Farmasi']),
            'activity_type' => ActivityType::Exam,
            'date' => now()->addDays(7)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'participants_count' => 30,
            'status' => BookingStatus::Pending,
            'source' => BookingSource::Public,
        ];
    }

    public function at(CarbonInterface|string $date, string $start, string $end): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date instanceof CarbonInterface ? $date->toDateString() : $date,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Pending,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Rejected,
            'rejection_reason' => 'Lab dipakai untuk kegiatan lain.',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Completed,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Cancelled,
        ]);
    }
}
