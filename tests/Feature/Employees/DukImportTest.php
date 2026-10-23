<?php

use App\Exceptions\DukImportException;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Employees\DukImporter;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\DukFile;

beforeEach(function () {
    $this->nursing = Department::factory()->create(['name' => 'Jurusan Keperawatan', 'short_name' => 'Keperawatan']);
    $this->environment = Department::factory()->create(['name' => 'Jurusan Kesehatan Lingkungan', 'short_name' => 'Kesling']);
    $this->directorate = Department::factory()->create(['name' => 'Direktorat', 'short_name' => 'Direktorat']);
    $this->importer = app(DukImporter::class);

    $this->employees = [
        [
            'name' => 'Dr. Contoh Satu, M.Kes', 'nip' => "'197001012000031001", 'rank' => 'IV/a', 'position' => 'Dosen Lektor Kepala (JFT)',
            'placement' => 'Keperawatan', 'nik' => '7171000000000001', 'birth_place' => 'Tondano', 'birth_date' => '01 Januari 1970',
            'phone' => '081200000001', 'personal_email' => 'satu.pribadi@gmail.com', 'office_email' => 'satu@kemkes.go.id',
        ],
        [
            'name' => 'Contoh Dua, S.Kom', 'nip' => '199001012020121002', 'rank' => 'III/a', 'position' => 'Pranata Komputer Ahli Pertama (JFT)',
            'placement' => 'Direktorat / Unit', 'structural_position' => 'Kepala Unit Teknologi Informasi',
            'nik' => '7171000000000002', 'phone' => '081200000002', 'personal_email' => 'dua.pribadi@gmail.com',
        ],
        [
            'name' => 'Contoh Tiga, SKM', 'nip' => '198501012010012003', 'placement' => 'Kesehatan Lingkungan',
        ],
        [
            'name' => 'Contoh Pensiun', 'nip' => '196001011985031004', 'placement' => 'Pensiun',
        ],
    ];
});

it('mengimpor pegawai beserta unit dan periode DUK', function () {
    $result = $this->importer->import(DukFile::make($this->employees));

    expect($result->created)->toBe(4)
        ->and($result->updated)->toBe(0)
        ->and($result->period)->toBe('Agustus 2026')
        ->and(Employee::count())->toBe(4);

    $first = Employee::query()->where('nip', '197001012000031001')->sole();

    expect($first)
        ->name->toBe('Dr. Contoh Satu, M.Kes')
        ->rank->toBe('IV/a')
        ->position->toBe('Dosen Lektor Kepala (JFT)')
        ->department_id->toBe($this->nursing->id)
        ->office_email->toBe('satu@kemkes.go.id')
        ->is_active->toBeTrue()
        ->last_seen_period->toBe('Agustus 2026');

    expect(Employee::query()->where('nip', '199001012020121002')->sole()->department_id)->toBe($this->directorate->id)
        ->and(Employee::query()->where('nip', '198501012010012003')->sole()->department_id)->toBe($this->environment->id)
        ->and(Employee::query()->where('nip', '196001011985031004')->sole()->is_active)->toBeFalse();
});

it('tidak menyimpan NIK, tempat/tanggal lahir, telepon, dan email pribadi', function () {
    $this->importer->import(DukFile::make($this->employees));

    expect(Schema::getColumnListing('employees'))->not->toContain('nik', 'birth_date', 'birth_place', 'phone', 'personal_email');

    $stored = json_encode(Employee::query()->get()->toArray());

    expect($stored)
        ->not->toContain('7171000000000001')
        ->not->toContain('Tondano')
        ->not->toContain('1970-01-01')
        ->not->toContain('081200000001')
        ->not->toContain('satu.pribadi@gmail.com');
});

it('memperbarui berdasarkan NIP dan menonaktifkan pegawai yang tidak ada lagi di DUK', function () {
    $this->importer->import(DukFile::make($this->employees));

    $next = $this->employees;
    $next[0]['rank'] = 'IV/b';
    unset($next[2]);

    $result = $this->importer->import(DukFile::make(array_values($next), 'SEPTEMBER 2026'));

    expect($result->created)->toBe(0)
        ->and($result->updated)->toBe(3)
        ->and($result->deactivated)->toBe(1)
        ->and(Employee::count())->toBe(4)
        ->and(Employee::query()->where('nip', '197001012000031001')->sole()->rank)->toBe('IV/b')
        ->and(Employee::query()->where('nip', '198501012010012003')->sole()->is_active)->toBeFalse()
        ->and(Employee::query()->where('nip', '197001012000031001')->sole()->last_seen_period)->toBe('September 2026');
});

it('melewati baris dengan NIP tidak valid atau ganda', function () {
    $result = $this->importer->import(DukFile::make([
        ...$this->employees,
        ['name' => 'NIP Pendek', 'nip' => '12345'],
        ['name' => 'NIP Ganda', 'nip' => '199001012020121002'],
    ]));

    expect($result->created)->toBe(4)
        ->and($result->skipped)->toHaveCount(2)
        ->and($result->skipped[0])->toContain('NIP tidak valid')
        ->and($result->skipped[1])->toContain('NIP ganda');
});

it('mengisi nama & NIP Kepala Unit di Pengaturan dari jabatan struktural', function () {
    $result = $this->importer->import(DukFile::make($this->employees));

    $settings = app(SettingsService::class);

    expect($result->head?->nip)->toBe('199001012020121002')
        ->and($settings->get('identity.head_name'))->toBe('Contoh Dua, S.Kom')
        ->and($settings->get('identity.head_nip'))->toBe('199001012020121002');
});

it('tidak mengubah Kepala Unit bila jabatan struktural tidak ditemukan', function () {
    app(SettingsService::class)->set('identity.head_name', 'Diisi Manual');

    $result = $this->importer->import(DukFile::make([$this->employees[0]]));

    expect($result->head)->toBeNull()
        ->and(app(SettingsService::class)->get('identity.head_name'))->toBe('Diisi Manual');
});

it('menolak file yang bukan DUK', function (Closure $makeFile, string $message) {
    expect(fn () => $this->importer->import($makeFile()))->toThrow(DukImportException::class, $message);
})->with([
    'sheet tidak ada' => [fn () => DukFile::make([], sheet: 'Sheet1'), 'Sheet "LAPORAN DUK" tidak ditemukan'],
    'bukan Excel' => [function () {
        $path = tempnam(sys_get_temp_dir(), 'duk').'.xlsx';
        file_put_contents($path, 'bukan file excel');

        return $path;
    }, 'tidak bisa dibaca'],
]);

it('menolak format tanpa kolom NIP', function () {
    $path = DukFile::make($this->employees);
    $book = IOFactory::load($path);
    $book->getSheetByName('LAPORAN DUK')->setCellValue('G7', 'Nomor Pegawai');
    (new Xlsx($book))->save($path);

    expect(fn () => $this->importer->import($path))->toThrow(DukImportException::class, 'Format DUK tidak dikenali');
});
