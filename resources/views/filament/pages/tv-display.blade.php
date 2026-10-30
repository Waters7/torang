<x-filament-panels::page>
    <div wire:poll.60s x-data style="background:#0b1f1d;color:#fff;padding:28px;border-radius:16px;min-height:75vh;overflow:auto" x-ref="screen">
        <div style="display:flex;justify-content:space-between;gap:15px;flex-wrap:wrap"><div><h2 style="font-size:24px;font-weight:700">TORANG · Pusat Operasi IT</h2><p style="color:#a9c8c0;margin-top:8px">{{ $updatedAt }} · pembaruan otomatis setiap menit</p></div><button type="button" style="padding:10px 16px;border:1px solid #6ac7bb;border-radius:8px" x-on:click="document.fullscreenElement ? document.exitFullscreen() : $refs.screen.requestFullscreen()">Layar penuh</button></div>
        @foreach ($groups as $group)
            <h3 style="margin:28px 0 14px;color:#70dbcd;font-size:18px;font-weight:600">{{ $group['title'] }}</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));gap:14px">@forelse ($group['items'] as $item)<article style="padding:18px;border:1px solid #34524b;border-radius:12px;background:#12352d"><h4 style="font-size:17px;font-weight:600">{{ $item['label'] }}</h4><p style="margin-top:10px;color:{{ in_array($item['status'], ['Offline', 'Baru']) ? '#ffa7a7' : (in_array($item['status'], ['Lambat', 'Menunggu', 'Maintenance']) ? '#ffcf75' : '#85e9b4') }}">{{ $item['status'] }}</p><p style="margin-top:8px;font-size:13px;color:#b7cec8">{{ $item['detail'] }}</p></article>@empty<p style="color:#b7cec8">Tidak ada data.</p>@endforelse</div>
        @endforeach
    </div>
</x-filament-panels::page>
