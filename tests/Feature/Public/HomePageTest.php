<?php

use App\Services\SettingsService;
use Inertia\Testing\AssertableInertia as Assert;

it('menampilkan beranda publik dengan layout TORANG', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Home')
            ->where('site.institution', 'Poltekkes Kemenkes Manado')
            ->where('site.unit', 'UPT TIK'));
});

it('mengambil kontak helpdesk dari pengaturan', function () {
    app(SettingsService::class)->setMany([
        'identity.helpdesk_whatsapp' => '081234567890',
        'identity.helpdesk_email' => 'helpdesk@example.ac.id',
    ]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('site.helpdesk.whatsapp', '081234567890')
            ->where('site.helpdesk.email', 'helpdesk@example.ac.id'));
});

it('mengirim null untuk kontak helpdesk yang belum diatur', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('site.helpdesk.whatsapp', null)
            ->where('site.helpdesk.email', null));
});

it('hanya membagikan identitas dan kontak helpdesk ke halaman publik', function () {
    app(SettingsService::class)->set('identity.head_nip', '199001012020121001');

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('site', fn (Assert $site) => $site
                ->hasAll(['institution', 'unit', 'helpdesk'])
                ->has('helpdesk', 3)))
        ->assertDontSee('199001012020121001');
});
