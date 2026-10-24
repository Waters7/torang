@php
    /** @var \App\Models\UkomReport $record */
    $record = $getRecord();
    $muted = 'font-size: 0.875rem; color: var(--gray-500);';
    $box = 'border: 1px dashed var(--gray-300); border-radius: 0.75rem; padding: 1.25rem; text-align: center;';
@endphp

{{-- Pratinjau PDF laporan UKOM; polling sampai PDF selesai dibuat queue. --}}
<div>
    @if ($record->pdf_path)
        <iframe
            src="{{ route('admin.ukom-reports.file', [$record, 'pdf', 'inline' => 1]) }}#view=FitH"
            title="Pratinjau {{ $record->letter_number }}"
            style="width: 100%; height: 75vh; border: 1px solid var(--gray-200); border-radius: 0.75rem; background: #fff;"
        ></iframe>
    @elseif (! $record->docx_path)
        <div style="{{ $box }}">
            <p style="font-weight: 600;">Dokumen belum terbentuk.</p>
            @if ($record->pdf_error)
                <p style="{{ $muted }}">{{ $record->pdf_error }}</p>
            @endif
        </div>
    @elseif (! $libreOfficeAvailable)
        <div style="{{ $box }}">
            <p style="font-weight: 600;">Pratinjau PDF belum tersedia.</p>
            <p style="{{ $muted }}">LibreOffice belum terpasang di server, jadi dokumen hanya tersedia dalam format Word. Gunakan tombol "Unduh Word".</p>
        </div>
    @elseif ($record->pdf_error)
        <div style="{{ $box }}">
            <p style="font-weight: 600;">PDF gagal dibuat.</p>
            <p style="{{ $muted }}">{{ $record->pdf_error }} Dokumen Word tetap bisa diunduh.</p>
        </div>
    @else
        <div wire:poll.5s style="{{ $box }}">
            <p style="font-weight: 600;">PDF sedang dibuat…</p>
            <p style="{{ $muted }}">Halaman ini diperbarui otomatis. Pastikan worker queue berjalan.</p>
        </div>
    @endif
</div>
