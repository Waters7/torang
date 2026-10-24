<?php

namespace App\Actions\Hosting;

use App\Models\HostedService;
use App\Models\ServicePayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Catat pembayaran/perpanjangan: simpan riwayat di service_payments, lalu majukan tanggal bayar terakhir
 * dan "berlaku sampai". Pembayaran lama yang dicatat belakangan tidak memundurkan tanggal yang sudah ada.
 */
class RecordServicePayment
{
    /**
     * @param  array{paid_at: mixed, period_start: mixed, period_end: mixed, amount: mixed, proof_path?: ?string, notes?: ?string}  $data
     */
    public function handle(HostedService $service, array $data, ?User $by): ServicePayment
    {
        $paidAt = CarbonImmutable::parse($data['paid_at'])->startOfDay();
        $periodStart = CarbonImmutable::parse($data['period_start'])->startOfDay();
        $periodEnd = CarbonImmutable::parse($data['period_end'])->startOfDay();

        if ($periodEnd->lessThanOrEqualTo($periodStart)) {
            throw ValidationException::withMessages(['period_end' => 'Akhir periode harus setelah awal periode.']);
        }

        return DB::transaction(function () use ($service, $data, $by, $paidAt, $periodStart, $periodEnd): ServicePayment {
            $payment = $service->payments()->create([
                'paid_at' => $paidAt,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'amount' => $data['amount'],
                'proof_path' => $data['proof_path'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $by?->getKey(),
            ]);

            if ($service->last_paid_at === null || $paidAt->greaterThan($service->last_paid_at)) {
                $service->last_paid_at = $paidAt;
            }

            if ($periodEnd->greaterThan($service->expires_at)) {
                $service->expires_at = $periodEnd;
            }

            $service->save();

            return $payment;
        });
    }
}
