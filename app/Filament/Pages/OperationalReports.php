<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Exports\OperationalReportExport;
use App\Filament\Concerns\PrefixesFormValidationErrors;
use App\Filament\NavigationGroup;
use App\Models\Department;
use App\Models\Lab;
use App\Services\Reports\OperationalReports as Reports;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class OperationalReports extends Page
{
    use PrefixesFormValidationErrors;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::LabCbt;

    protected static ?string $title = 'Laporan CBT & SLA';

    protected static ?string $slug = 'laporan-operasional';

    protected string $view = 'filament.pages.operational-reports';

    public ?array $data = [];

    public ?array $report = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->is_active && auth()->user()->can(Permission::ReportsView->value);
    }

    public function mount(): void
    {
        $this->form->fill(['kind' => 'cbt', 'start' => now()->startOfMonth()->toDateString(), 'end' => now()->toDateString()]);
        $this->generate();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(3)->components([
            Select::make('kind')->label('Laporan')->options(['cbt' => 'Pemakaian CBT', 'sla' => 'Uptime & SLA'])->required(),
            DatePicker::make('start')->label('Mulai')->required(), DatePicker::make('end')->label('Sampai')->required()->afterOrEqual('start'),
            Select::make('lab_id')->label('Lab (laporan CBT)')->options(fn () => Lab::orderBy('name')->pluck('name', 'id'))->searchable(),
            Select::make('department_id')->label('Penyelenggara / jurusan monitor')->options(fn () => Department::ordered()->pluck('name', 'id'))->searchable(),
        ]);
    }

    public function generate(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        try {
            $this->report = app(Reports::class)->build($data);
        } catch (ValidationException $exception) {
            throw $this->prefixValidationErrors($exception);
        }
    }

    public function excel()
    {
        $this->generate();

        return Excel::download(new OperationalReportExport($this->report), 'laporan-operasional.xlsx');
    }

    public function pdf()
    {
        $this->generate();
        $bytes = Pdf::loadView('pdf.operational-report', $this->report)->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->output();

        return response()->streamDownload(fn () => print ($bytes), 'laporan-operasional.pdf');
    }
}
