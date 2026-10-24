<?php

namespace App\Filament\Resources\HostedServices\RelationManagers;

use App\Models\ServicePayment;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static bool $isLazy = false;

    protected static ?string $title = 'Riwayat pembayaran';

    protected static ?string $modelLabel = 'pembayaran';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('recordedBy'))
            ->defaultSort('paid_at', 'desc')
            ->emptyStateHeading('Belum ada pembayaran tercatat')
            ->emptyStateDescription('Gunakan tombol "Catat pembayaran" di atas.')
            ->columns([
                TextColumn::make('paid_at')
                    ->label('Tanggal bayar')
                    ->date('j M Y'),
                TextColumn::make('period')
                    ->label('Periode')
                    ->state(fn (ServicePayment $record): string => Format::date($record->period_start).' – '.Format::date($record->period_end)),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn ($state): string => Format::rupiah($state))
                    ->fontFamily('mono'),
                TextColumn::make('recordedBy.name')
                    ->label('Dicatat oleh')
                    ->placeholder('-'),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(40)
                    ->placeholder('-'),
            ])
            ->recordActions([
                Action::make('downloadProof')
                    ->label('Bukti')
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->color('gray')
                    ->authorize('downloadProof')
                    ->url(fn (ServicePayment $record): string => route('admin.service-payments.proof', $record), shouldOpenInNewTab: true),
                DeleteAction::make()
                    ->modalDescription('Tanggal "berlaku sampai" layanan tidak berubah otomatis; sesuaikan lewat Ubah layanan bila perlu.')
                    ->after(function (ServicePayment $record): void {
                        if ($record->proof_path) {
                            Storage::disk('local')->delete($record->proof_path);
                        }
                    }),
            ]);
    }
}
