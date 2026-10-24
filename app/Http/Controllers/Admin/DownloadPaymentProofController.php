<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServicePayment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bukti bayar layanan hosting disimpan di disk privat dan hanya bisa diunduh petugas berwenang.
 */
class DownloadPaymentProofController extends Controller
{
    public function __invoke(ServicePayment $servicePayment): StreamedResponse
    {
        Gate::authorize('downloadProof', $servicePayment);

        abort_unless(Storage::disk('local')->exists($servicePayment->proof_path), 404);

        $name = str($servicePayment->service->identifier)->replace('.', ' ')->slug().'-'.$servicePayment->paid_at->toDateString();

        return Storage::disk('local')->download($servicePayment->proof_path, "bukti-bayar-{$name}.pdf");
    }
}
