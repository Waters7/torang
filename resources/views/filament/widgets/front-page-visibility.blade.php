<x-filament-widgets::widget>
    <x-filament::section
        heading="Tampilan halaman depan"
        description="Pilih bagian yang tampil di beranda publik. Perubahan langsung berlaku dan tercatat di audit log."
        icon="heroicon-o-globe-alt"
    >
        <x-slot name="afterHeader">
            <x-filament::link :href="url('/')" target="_blank" icon="heroicon-o-arrow-top-right-on-square" size="sm">
                Lihat halaman depan
            </x-filament::link>
        </x-slot>

        {{ $this->form }}
    </x-filament::section>
</x-filament-widgets::widget>
