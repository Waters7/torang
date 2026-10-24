<?php

use App\Actions\Hosting\RecordServicePayment;
use App\Enums\BillingCycle;
use App\Enums\HostedServiceState;
use App\Enums\Role;
use App\Exports\HostingExport;
use App\Exports\Sheets\ProviderBudgetSheet;
use App\Exports\Sheets\ServicePaymentsSheet;
use App\Filament\Resources\HostedServices\Pages\CreateHostedService;
use App\Filament\Resources\HostedServices\Pages\ListHostedServices;
use App\Filament\Resources\HostedServices\Pages\ViewHostedService;
use App\Filament\Resources\Providers\Pages\ManageProviders;
use App\Models\HostedService;
use App\Models\Provider;
use App\Services\Hosting\HostingBudget;
use Carbon\CarbonImmutable;
use Database\Seeders\ProviderSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
    $this->staff = userWithRole(Role::ItStaff);
});

describe('status & biaya', function () {
    it('menghitung status turunan dari tanggal berakhir', function (int $daysLeft, HostedServiceState $state) {
        $service = HostedService::factory()->expiresInDays($daysLeft)->create();

        expect($service->state())->toBe($state)
            ->and(HostedService::query()->inState($state)->pluck('id')->all())->toBe([$service->id]);
    })->with([
        '31 hari' => [31, HostedServiceState::Active],
        '30 hari' => [30, HostedServiceState::Expiring],
        'hari ini' => [0, HostedServiceState::Expiring],
        'kemarin' => [-1, HostedServiceState::Expired],
    ]);

    it('menandai layanan yang dihentikan sebagai Dihentikan apa pun tanggalnya', function () {
        $service = HostedService::factory()->expiresInDays(-10)->stopped()->create();

        expect($service->state())->toBe(HostedServiceState::Stopped)
            ->and(HostedService::query()->inState(HostedServiceState::Expired)->count())->toBe(0);
    });

    it('menyetahunkan biaya sesuai siklus tagihan', function (BillingCycle $cycle, int $cost, float $annual) {
        expect(HostedService::factory()->make(['billing_cycle' => $cycle, 'cost' => $cost])->annualCost())->toBe($annual);
    })->with([
        [BillingCycle::Monthly, 100000, 1200000.0],
        [BillingCycle::Quarterly, 330000, 1320000.0],
        [BillingCycle::Semiannual, 600000, 1200000.0],
        [BillingCycle::Annual, 250000, 250000.0],
        [BillingCycle::Biennial, 2400000, 1200000.0],
        [BillingCycle::Triennial, 3600000, 1200000.0],
    ]);

    it('merekap estimasi biaya per provider dari layanan aktif saja', function () {
        $domainesia = Provider::factory()->create(['name' => 'Domainesia']);
        $hostinger = Provider::factory()->create(['name' => 'Hostinger']);
        HostedService::factory()->for($domainesia)->create(['billing_cycle' => BillingCycle::Annual, 'cost' => 250000]);
        HostedService::factory()->for($domainesia)->create(['billing_cycle' => BillingCycle::Monthly, 'cost' => 100000]);
        HostedService::factory()->for($hostinger)->stopped()->create(['cost' => 999999]);

        expect(app(HostingBudget::class)->perProvider()->all())->toBe([
            ['provider' => 'Domainesia', 'services' => 2, 'annual_cost' => 1450000.0],
        ])->and(app(HostingBudget::class)->totalAnnualCost())->toBe(1450000.0);
    });
});

describe('panel', function () {
    it('membuat layanan baru', function () {
        $provider = Provider::factory()->create();
        $this->actingAs($this->staff);

        Livewire::test(CreateHostedService::class)
            ->fillForm([
                'provider_id' => $provider->id,
                'type' => 'domain',
                'name' => 'Domain kampus',
                'identifier' => 'example.ac.id',
                'billing_cycle' => 'annual',
                'cost' => 75000,
                'expires_at' => '2027-03-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(HostedService::query()->sole())
            ->identifier->toBe('example.ac.id')
            ->expires_at->toDateString()->toBe('2027-03-01');
    });

    it('menampilkan ringkasan dan daftar yang segera jatuh tempo', function () {
        HostedService::factory()->expiresInDays(5)->create(['name' => 'VPS Akademik']);
        HostedService::factory()->expiresInDays(200)->create(['name' => 'Domain Utama']);
        $this->actingAs($this->staff);

        $this->get('/admin/hosting')
            ->assertOk()
            ->assertSee('Segera jatuh tempo')
            ->assertSee('Estimasi biaya per tahun')
            ->assertSee('5 hari lagi');
    });

    it('tidak menghapus provider yang masih punya layanan', function () {
        $used = Provider::factory()->create();
        HostedService::factory()->for($used)->create();
        $unused = Provider::factory()->create();
        $this->actingAs($this->staff);

        Livewire::test(ManageProviders::class)
            ->assertTableActionHidden('delete', $used)
            ->assertTableActionVisible('delete', $unused);
    });

    it('menyemai provider awal Domainesia, Jagoanhosting, Hostinger', function () {
        $this->seed(ProviderSeeder::class);
        $this->seed(ProviderSeeder::class);

        expect(Provider::query()->orderBy('name')->pluck('name')->all())->toBe(['Domainesia', 'Hostinger', 'Jagoanhosting']);
    });
});

describe('catat pembayaran', function () {
    it('mencatat pembayaran dan memperbarui tanggal berlaku', function () {
        // Unggahan sementara Livewire dibersihkan bila waktu dimajukan, jadi test ini memakai waktu nyata.
        $this->travelBack();
        Storage::fake('local');
        $service = HostedService::factory()->expiresInDays(5)->create(['billing_cycle' => BillingCycle::Annual, 'cost' => 250000]);
        $start = today()->addDays(5);
        $this->actingAs($this->staff);

        Livewire::test(ListHostedServices::class)
            ->mountTableAction('recordPayment', $service)
            ->assertSchemaStateSet([
                'paid_at' => today()->toDateString(),
                'period_start' => $start->toDateString(),
                'period_end' => $start->copy()->addYear()->toDateString(),
                'amount' => '250000.00',
            ])
            ->fillForm([
                'proof_path' => UploadedFile::fake()->create('bukti.pdf', 120, 'application/pdf'),
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $service->refresh();
        $payment = $service->payments()->sole();

        expect($service->expires_at->toDateString())->toBe($start->copy()->addYear()->toDateString())
            ->and($service->last_paid_at->toDateString())->toBe(today()->toDateString())
            ->and($payment->recorded_by)->toBe($this->staff->id)
            ->and((float) $payment->amount)->toBe(250000.0)
            ->and($payment->proof_path)->toStartWith('hosting/payments/');

        Storage::disk('local')->assertExists($payment->proof_path);
    });

    it('menolak bukti bayar selain PDF dan periode terbalik', function () {
        Storage::fake('local');
        $service = HostedService::factory()->create();
        $this->actingAs($this->staff);

        Livewire::test(ListHostedServices::class)
            ->callTableAction('recordPayment', $service, data: [
                'paid_at' => '2026-11-02',
                'period_start' => '2026-12-01',
                'period_end' => '2026-11-01',
                'amount' => 100000,
                'proof_path' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertHasTableActionErrors(['period_end', 'proof_path']);

        expect($service->payments()->count())->toBe(0);
    });

    it('tidak memundurkan tanggal berlaku saat mencatat pembayaran lama', function () {
        $service = HostedService::factory()->create(['expires_at' => '2027-06-01', 'last_paid_at' => '2026-06-01']);

        app(RecordServicePayment::class)->handle($service, [
            'paid_at' => '2025-06-01',
            'period_start' => '2025-06-01',
            'period_end' => '2026-06-01',
            'amount' => 250000,
        ], $this->staff);

        expect($service->fresh())
            ->expires_at->toDateString()->toBe('2027-06-01')
            ->last_paid_at->toDateString()->toBe('2026-06-01')
            ->and($service->payments()->count())->toBe(1);
    });

    it('mengunduh bukti bayar hanya untuk petugas berwenang', function () {
        Storage::fake('local');
        $service = HostedService::factory()->create(['identifier' => 'example.ac.id']);
        $payment = app(RecordServicePayment::class)->handle($service, [
            'paid_at' => '2026-11-01',
            'period_start' => '2026-11-01',
            'period_end' => '2027-11-01',
            'amount' => 250000,
            'proof_path' => UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf')->store('hosting/payments', 'local'),
        ], $this->staff);
        $url = route('admin.service-payments.proof', $payment);

        $this->get($url)->assertRedirect();
        $this->actingAs(userWithRole(Role::CbtManager))->get($url)->assertForbidden();
        $this->actingAs(userWithRole(Role::HeadOfUnit))->get($url)
            ->assertOk()
            ->assertDownload('bukti-bayar-example-ac-id-2026-11-01.pdf');
    });

    it('tidak menghapus layanan yang sudah punya riwayat pembayaran', function () {
        $service = HostedService::factory()->create();
        app(RecordServicePayment::class)->handle($service, [
            'paid_at' => '2026-11-01', 'period_start' => '2026-11-01', 'period_end' => '2027-11-01', 'amount' => 1,
        ], $this->staff);

        expect($this->staff->can('delete', $service->fresh()))->toBeFalse()
            ->and($this->staff->can('delete', HostedService::factory()->create()))->toBeTrue();
    });
});

describe('ekspor', function () {
    it('mengunduh Excel dan PDF', function () {
        HostedService::factory()->create();
        $this->actingAs(userWithRole(Role::HeadOfUnit));

        Livewire::test(ListHostedServices::class)
            ->callAction('exportExcel', data: ['year' => 2026])
            ->assertFileDownloaded('hosting-domain-2026-11-02.xlsx');

        Livewire::test(ListHostedServices::class)
            ->callAction('exportPdf')
            ->assertFileDownloaded('rekap-hosting-domain-2026-11-02.pdf');
    });

    it('menyusun sheet anggaran per provider dan pembayaran per tahun', function () {
        $provider = Provider::factory()->create(['name' => 'Domainesia']);
        $service = HostedService::factory()->for($provider)->create(['billing_cycle' => BillingCycle::Monthly, 'cost' => 100000]);
        app(RecordServicePayment::class)->handle($service, ['paid_at' => '2026-03-01', 'period_start' => '2026-03-01', 'period_end' => '2026-04-01', 'amount' => 100000], $this->staff);
        app(RecordServicePayment::class)->handle($service, ['paid_at' => '2025-03-01', 'period_start' => '2025-03-01', 'period_end' => '2025-04-01', 'amount' => 90000], $this->staff);

        expect((new HostingExport(2026))->sheets())->toHaveCount(3)
            ->and((new ProviderBudgetSheet)->array())->toBe([
                ['Domainesia', 1, 1200000.0],
                ['Total', 1, 1200000.0],
            ]);

        $payments = (new ServicePaymentsSheet(2026))->array();

        expect($payments)->toHaveCount(2)
            ->and($payments[0][6])->toBe(100000.0)
            ->and($payments[1])->toBe(['Total', null, null, null, null, null, 100000.0, null, null]);
    });
});

describe('hak akses', function () {
    it('memberi kepala unit akses lihat saja', function () {
        $service = HostedService::factory()->create();
        $this->actingAs(userWithRole(Role::HeadOfUnit));

        $this->get('/admin/hosting')->assertOk();
        $this->get("/admin/hosting/{$service->id}")->assertOk();
        $this->get('/admin/hosting/create')->assertForbidden();
        $this->get('/admin/provider')->assertOk();

        Livewire::test(ViewHostedService::class, ['record' => $service->getRouteKey()])
            ->assertActionHidden('recordPayment')
            ->assertActionHidden('edit');
    });

    it('tidak bisa dibuka pengelola CBT', function () {
        $this->actingAs(userWithRole(Role::CbtManager));

        $this->get('/admin/hosting')->assertForbidden();
        $this->get('/admin/provider')->assertForbidden();
    });
});
