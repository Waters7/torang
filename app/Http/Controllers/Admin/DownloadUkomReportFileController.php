<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UkomReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dokumen laporan UKOM (Word, PDF, scan bertanda tangan) di disk privat, hanya untuk pengelola laporan UKOM.
 * `?inline=1` menampilkan PDF di browser (pratinjau iframe).
 */
class DownloadUkomReportFileController extends Controller
{
    public function __invoke(Request $request, UkomReport $ukomReport, string $type): StreamedResponse
    {
        Gate::authorize('download', $ukomReport);

        [$path, $suffix] = match ($type) {
            'docx' => [$ukomReport->docx_path, '.docx'],
            'pdf' => [$ukomReport->pdf_path, '.pdf'],
            'scan' => [$ukomReport->signed_scan_path, '-ttd.pdf'],
            default => abort(404),
        };

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $name = $ukomReport->fileBaseName().$suffix;

        if ($type !== 'docx' && $request->boolean('inline')) {
            return Storage::disk('local')->response($path, $name, ['Content-Type' => 'application/pdf']);
        }

        return Storage::disk('local')->download($path, $name);
    }
}
