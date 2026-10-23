{{-- Wordmark ringkas TORANG untuk panel Filament (SPEC §11.3). Gaya inline karena CSS Filament sudah dikompilasi. --}}
@php($dark = $dark ?? false)

<div style="display: flex; align-items: center; gap: 0.625rem; height: 100%;">
    <x-torang-mark :faint="false" style="height: 2rem; width: 2rem; flex-shrink: 0;" />
    <div style="display: flex; flex-direction: column; gap: 0.2rem; line-height: 1;">
        <span style="font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.05rem; letter-spacing: 0.12em; color: {{ $dark ? '#FFFFFF' : '#0F2E2B' }};">TORANG</span>
        <span style="font-family: 'JetBrains Mono', monospace; font-weight: 500; font-size: 10px; letter-spacing: 0.08em; color: {{ $dark ? '#7FD8CC' : '#08796C' }};">POLTEKKES KEMENKES MANADO</span>
    </div>
</div>
