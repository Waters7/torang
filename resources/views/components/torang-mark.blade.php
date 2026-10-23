{{-- Simbol TORANG. Lihat SPEC §11.3 dan resources/svg/torang-mark.svg. --}}
@props([
    'faint' => true,
    'dark' => false,
])

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" role="img" aria-label="TORANG" {{ $attributes }}>
    @if ($faint)
        @php($faintFill = $dark ? 'rgba(255,255,255,0.08)' : '#E3F5F2')
        <rect x="0" y="35" width="26" height="26" rx="8" fill="{{ $faintFill }}" />
        <rect x="70" y="35" width="26" height="26" rx="8" fill="{{ $faintFill }}" />
        <rect x="0" y="70" width="26" height="26" rx="8" fill="{{ $faintFill }}" />
        <rect x="70" y="70" width="26" height="26" rx="8" fill="{{ $faintFill }}" />
    @endif
    <rect x="0" y="0" width="26" height="26" rx="8" fill="#1BBBCB" />
    <rect x="35" y="0" width="26" height="26" rx="8" fill="#04B6A3" />
    <circle cx="83" cy="13" r="13" fill="#D4E022" />
    <rect x="35" y="35" width="26" height="26" rx="8" fill="#04B6A3" />
    <rect x="35" y="70" width="26" height="26" rx="8" fill="#42B97C" />
</svg>
