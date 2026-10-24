<?php

use App\Actions\Ukom\FinalizeUkomReport;
use App\Actions\Ukom\SaveUkomReport;
use App\Enums\Role;
use App\Enums\UkomReportStatus;
use App\Exports\UkomReportsExport;
use App\Filament\Pages\UkomReportSettings;
use App\Filament\Resources\ExamOrganizers\Pages\ManageExamOrganizers;
use App\Filament\Resources\UkomReports\Pages\CreateUkomReport;
use App\Filament\Resources\UkomReports\Pages\EditUkomReport;
use App\Filament\Resources\UkomReports\Pages\ListUkomReports;
use App\Filament\Resources\UkomReports\Pages\ViewUkomReport;
use App\Models\DocumentTemplate;
use App\Models\ExamOrganizer;
use App\Models\UkomReport;
use App\Services\SettingsService;
use App\Services\Ukom\UkomTemplates;
use Database\Seeders\ExamOrganizerSeeder;
use Database\Seeders\SampleUkomReportSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    config(['torang.libreoffice.binary' => 'soffice-tidak-ada']);
    $this->seed(ExamOrganizerSeeder::class);
    $this->organizer = ExamOrganizer::query()->where('name', 'Kolegium TPD')->sole();
    $this->manager = userWithRole(Role::CbtManager);
});

function sampleUkomReport(array $overrides = []): UkomReport
{
    return app(SaveUkomReport::class)->create([
        ...SampleUkomReportSeeder::sampleData(ExamOrganizer::query()->where('name', 'Kolegium TPD')->sole()),
        'signer_name' => 'Nama Koordinator CBT',
        'signer_nip' => '199001012020121001',
        ...$overrides,
    ], null);
}

describe('wizard', function () {
    it('membuat draf laporan lengkap dengan rincian dan dokumen Word', function () {
        $this->actingAs($this->manager);

        Livewire::test(CreateUkomReport::class)
            ->fillForm([
                'period' => 2,
                'study_program' => 'D-3 Teknologi Bank Darah',
                'exam_date' => '2026-09-05',
                'letter_date' => '2026-09-05',
                'signer_name' => 'Nama Koordinator CBT',
                'signer_nip' => '199001012020121001',
                'items' => [
                    ['description' => 'Pembayaran Sewa CBT', 'qty_1' => 2, 'unit_1' => 'unit', 'unit_price' => 40000, 'display_text' => 'Pembayaran Sewa CBT 2 unit (Rp 40.000/unit)'],
                    ['description' => 'Konsumsi', 'qty_1' => 2, 'unit_1' => 'orang', 'qty_2' => 2, 'unit_2' => 'hari', 'note' => 'H-1 dan H', 'unit_price' => 50000, 'display_text' => 'Konsumsi 2 orang untuk 2 hari (H-1 dan H) Rp 50.000,00/org'],
                ],
            ])
            ->assertSchemaStateSet([
                'exam_organizer_id' => $this->organizer->id,
                'activity_name' => 'Uji Kompetensi Nasional Periode 2 Mahasiswa D-3 Teknologi Bank Darah',
                'subject' => 'Laporan dan Bukti Pertanggungjawaban atas Biaya Operasional Uji Kompetensi Nasional Periode 2 Mahasiswa D-3 Teknologi Bank Darah Tanggal 05 September 2026',
                'recipient_title' => 'Ketua Panitia Ujian Kompetensi Nasional Periode 2 Mahasiswa D-3 Teknologi Bank Darah',
                'recipient_org' => 'Kolegium TPD',
                'bank_account_number' => '1260013772123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $report = UkomReport::query()->sole();

        expect($report)
            ->status->toBe(UkomReportStatus::Draft)
            ->letter_number->toBe('PP.06.03/F.XXX.20/001/2026')
            ->created_by->toBe($this->manager->id)
            ->and((float) $report->total_amount)->toBe(280000.0)
            ->and($report->items)->toHaveCount(2);

        Storage::disk('local')->assertExists($report->docx_path);
    });

    it('tidak menimpa teks yang sudah diedit manual', function () {
        $this->actingAs($this->manager);

        Livewire::test(CreateUkomReport::class)
            ->fillForm(['period' => 2, 'study_program' => 'D-3 Teknologi Bank Darah'])
            ->fillForm(['activity_name' => 'UKOM Nasional TBD'])
            ->fillForm(['period' => 3])
            ->assertSchemaStateSet([
                'activity_name' => 'UKOM Nasional TBD',
                'recipient_title' => 'Ketua Panitia Ujian Kompetensi Nasional Periode 3 Mahasiswa D-3 Teknologi Bank Darah',
            ]);
    });

    it('memvalidasi NIP 18 digit dan minimal satu rincian', function () {
        $this->actingAs($this->manager);

        Livewire::test(CreateUkomReport::class)
            ->fillForm(['period' => 2, 'study_program' => 'D-3 TBD', 'exam_date' => '2026-09-05', 'signer_name' => 'A', 'signer_nip' => '1990', 'items' => []])
            ->call('create')
            ->assertHasFormErrors(['signer_nip', 'items']);

        expect(UkomReport::count())->toBe(0);
    });

    it('mengubah draf tanpa mengganti nomor surat', function () {
        $report = sampleUkomReport();
        $this->actingAs($this->manager);

        Livewire::test(EditUkomReport::class, ['record' => $report->getRouteKey()])
            ->assertSchemaStateSet(['auto_number' => true, 'letter_number' => 'PP.06.03/F.XXX.20/023/2026'])
            ->fillForm(['period' => 3])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($report->fresh())
            ->period->toBe(3)
            ->letter_number->toBe('PP.06.03/F.XXX.20/023/2026')
            ->activity_name->toBe('Uji Kompetensi Nasional Periode 3 Mahasiswa D-3 Teknologi Bank Darah')
            ->and($report->items()->count())->toBe(2);
    });
});

describe('alur laporan', function () {
    it('memfinalkan laporan sehingga tidak bisa diubah lagi', function () {
        $report = sampleUkomReport();
        $this->actingAs($this->manager);

        Livewire::test(ViewUkomReport::class, ['record' => $report->getRouteKey()])
            ->callAction('finalize')
            ->assertNotified('Laporan difinalkan');

        expect($report->fresh())->status->toBe(UkomReportStatus::Final)->finalized_at->not->toBeNull();

        $this->get("/admin/laporan-ukom/{$report->id}/edit")->assertForbidden();
        Livewire::test(ViewUkomReport::class, ['record' => $report->getRouteKey()])
            ->assertActionHidden('edit')
            ->assertActionHidden('finalize')
            ->assertActionVisible('uploadSignedScan');
    });

    it('mengunggah scan bertanda tangan setelah final', function () {
        $report = app(FinalizeUkomReport::class)->handle(sampleUkomReport());
        $this->actingAs($this->manager);

        Livewire::test(ViewUkomReport::class, ['record' => $report->getRouteKey()])
            ->callAction('uploadSignedScan', data: ['scan' => UploadedFile::fake()->create('scan.pdf', 300, 'application/pdf')])
            ->assertHasNoActionErrors();

        $report->refresh();

        expect($report->status)->toBe(UkomReportStatus::Signed)
            ->and($report->signed_scan_path)->toBe("reports/ukom/{$report->id}/{$report->fileBaseName()}-ttd.pdf");

        Storage::disk('local')->assertExists($report->signed_scan_path);
        $this->get(route('admin.ukom-reports.file', [$report, 'scan']))->assertOk()->assertDownload("{$report->fileBaseName()}-ttd.pdf");
    });

    it('menduplikat laporan menjadi draf baru bernomor baru', function () {
        $report = app(FinalizeUkomReport::class)->handle(sampleUkomReport());
        $this->actingAs($this->manager);

        Livewire::test(ViewUkomReport::class, ['record' => $report->getRouteKey()])
            ->callAction('duplicate')
            ->assertRedirect();

        $copy = UkomReport::query()->latest('id')->first();

        expect($copy->isNot($report))->toBeTrue()
            ->and($copy->status)->toBe(UkomReportStatus::Draft)
            ->and($copy->letter_number)->toBe('PP.06.03/F.XXX.20/024/'.now()->year)
            ->and($copy->letter_date->toDateString())->toBe(today()->toDateString())
            ->and($copy->items->pluck('display_text')->all())->toBe($report->items->pluck('display_text')->all())
            ->and($copy->signed_scan_path)->toBeNull();
    })->skip(fn () => now()->year !== 2026, 'Nomor contoh memakai tahun 2026.');

    it('mengarsipkan dan memulihkan laporan tanpa melepas nomornya', function () {
        $report = sampleUkomReport();
        $this->actingAs($this->manager);

        Livewire::test(ViewUkomReport::class, ['record' => $report->getRouteKey()])->callAction('delete');

        expect($report->fresh()->trashed())->toBeTrue();
        Livewire::test(ListUkomReports::class)->assertCanNotSeeTableRecords([$report]);
        $this->get("/admin/laporan-ukom/{$report->id}")->assertOk();

        Livewire::test(ViewUkomReport::class, ['record' => $report->getRouteKey()])->callAction('restore');

        expect($report->fresh()->trashed())->toBeFalse();
    });

    it('menampilkan keterangan bila LibreOffice belum tersedia', function () {
        $report = sampleUkomReport();
        $this->actingAs($this->manager);

        $this->get("/admin/laporan-ukom/{$report->id}")
            ->assertOk()
            ->assertSee('LibreOffice belum terpasang di server');
    });
});

describe('unduhan & ekspor', function () {
    it('mengunduh Word hanya untuk pengelola laporan UKOM', function () {
        $report = sampleUkomReport();
        $url = route('admin.ukom-reports.file', [$report, 'docx']);

        $this->get($url)->assertRedirect();
        $this->actingAs(userWithRole(Role::ItStaff))->get($url)->assertForbidden();
        $this->actingAs($this->manager)->get($url)->assertOk()->assertDownload("{$report->fileBaseName()}.docx");
        $this->get(route('admin.ukom-reports.file', [$report, 'pdf']))->assertNotFound();
    });

    it('mengunduh rekap Excel dan menghitung total per tahun tanpa arsip', function () {
        sampleUkomReport();
        sampleUkomReport(['letter_number' => 'PP.06.03/F.XXX.20/024/2026'])->delete();
        sampleUkomReport(['letter_number' => 'PP.06.03/F.XXX.20/001/2025', 'letter_date' => '2025-11-03']);
        $this->actingAs(userWithRole(Role::HeadOfUnit));

        Livewire::test(ListUkomReports::class)
            ->callAction('exportExcel', data: ['year' => 2026])
            ->assertFileDownloaded('rekap-laporan-ukom-2026.xlsx');

        expect(UkomReportsExport::yearlyTotals())->toBe([
            ['year' => 2026, 'reports' => 1, 'total' => 280000.0],
            ['year' => 2025, 'reports' => 1, 'total' => 280000.0],
        ]);
    });
});

describe('kolegium & pengaturan', function () {
    it('tidak menghapus kolegium yang sudah dipakai laporan', function () {
        sampleUkomReport();
        $unused = ExamOrganizer::factory()->create();
        $this->actingAs($this->manager);

        Livewire::test(ManageExamOrganizers::class)
            ->assertTableActionHidden('delete', $this->organizer)
            ->assertTableActionVisible('delete', $unused);
    });

    it('menyimpan penanda tangan default dan pola nomor surat', function () {
        $this->actingAs($this->manager);

        Livewire::test(UkomReportSettings::class)
            ->fillForm([
                'signer_name' => 'Nama Baru',
                'signer_nip' => '198001012010121001',
                'number_pattern' => 'UKOM/{seq:4}/{roman}/{year}',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(app(SettingsService::class)->get('reports.ukom.signer_name'))->toBe('Nama Baru')
            ->and(app(SettingsService::class)->get('reports.ukom.number_pattern'))->toBe('UKOM/{seq:4}/{roman}/{year}');

        Livewire::test(UkomReportSettings::class)
            ->fillForm(['number_pattern' => 'TANPA-NOMOR/{year}', 'signer_nip' => '123'])
            ->call('save')
            ->assertHasFormErrors(['number_pattern', 'signer_nip']);
    });

    it('hanya super admin yang bisa mengganti template', function () {
        app(UkomTemplates::class)->active();
        $this->actingAs($this->manager);
        Livewire::test(UkomReportSettings::class)->assertTableActionHidden('uploadTemplate');

        $this->actingAs(userWithRole(Role::SuperAdmin));
        $docx = UploadedFile::fake()->createWithContent('template-baru.docx', file_get_contents(resource_path(UkomTemplates::BUNDLED)));

        Livewire::test(UkomReportSettings::class)
            ->callTableAction('uploadTemplate', data: ['template' => $docx])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Template versi 2 aktif');

        expect(DocumentTemplate::query()->where('is_active', true)->sole())
            ->version->toBe(2)
            ->original_name->toBe('template-baru.docx');
    });
});

describe('hak akses & data contoh', function () {
    it('bisa dibuka kepala unit dan pengelola CBT, tidak oleh petugas IT', function () {
        $this->actingAs(userWithRole(Role::HeadOfUnit))->get('/admin/laporan-ukom')->assertOk();
        $this->actingAs($this->manager)->get('/admin/laporan-ukom/create')->assertOk();

        $staff = userWithRole(Role::ItStaff);
        $this->actingAs($staff)->get('/admin/laporan-ukom')->assertForbidden();
        $this->actingAs($staff)->get('/admin/kolegium')->assertForbidden();
        $this->actingAs($staff)->get(UkomReportSettings::getUrl())->assertForbidden();
    });

    it('menyemai satu laporan final sesuai dokumen contoh', function () {
        app(SettingsService::class)->setMany(['reports.ukom.signer_name' => 'Nama Dari Pengaturan', 'reports.ukom.signer_nip' => '199001012020121001']);

        $this->seed(SampleUkomReportSeeder::class);
        $this->seed(SampleUkomReportSeeder::class);

        expect(UkomReport::query()->sole())
            ->status->toBe(UkomReportStatus::Final)
            ->letter_number->toBe('PP.06.03/F.XXX.20/023/2026')
            ->signer_name->toBe('Nama Dari Pengaturan')
            ->total_amount->toBe('280000.00');
    });
});
