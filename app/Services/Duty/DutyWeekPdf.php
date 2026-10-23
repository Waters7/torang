<?php

namespace App\Services\Duty;

use App\Models\DutyAssignment;
use App\Models\DutyWeek;
use App\Models\Shift;
use App\Services\SettingsService;
use App\Support\Format;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Jadwal petugas satu minggu dalam PDF A4 siap tanda tangan Kepala Unit (nama & NIP dari Pengaturan).
 */
class DutyWeekPdf
{
    public function __construct(private readonly SettingsService $settings) {}

    public function download(DutyWeek $week): Response
    {
        return Pdf::loadView('pdf.duty-week', $this->viewData($week))
            ->setPaper('a4')
            // Hanya karakter yang dipakai yang di-embed (laravel-dompdf mematikannya secara default).
            ->setOption('isFontSubsettingEnabled', true)
            ->download("jadwal-petugas-{$week->week_start->toDateString()}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(DutyWeek $week): array
    {
        $assignments = $week->assignments()->with(['staff', 'lab'])->get();

        $shifts = Shift::query()
            ->where(fn ($query) => $query->active()->orWhereIn('id', $assignments->pluck('shift_id')))
            ->ordered()
            ->get();

        // [tanggal][id shift][label cakupan] => [nama petugas, ...]
        $cells = [];

        $sorted = $assignments->sortBy(fn (DutyAssignment $assignment): string => ($assignment->lab?->code ?? '').$assignment->staff->name);

        foreach ($sorted as $assignment) {
            $cells[$assignment->date->toDateString()][$assignment->shift_id][$assignment->coverageLabel()][] = $assignment->staff->name;
        }

        return [
            'week' => $week,
            'shifts' => $shifts,
            'cells' => $cells,
            'institution' => $this->settings->get('identity.institution_name'),
            'unit' => $this->settings->get('identity.unit_name'),
            'headName' => $this->settings->get('identity.head_name'),
            'headNip' => $this->settings->get('identity.head_nip'),
            'place' => $this->settings->get('reports.ukom.letter_place', 'Manado'),
            'signedAt' => Format::date(now()),
        ];
    }
}
