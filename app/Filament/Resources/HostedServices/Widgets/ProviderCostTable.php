<?php

namespace App\Filament\Resources\HostedServices\Widgets;

use App\Services\Hosting\HostingBudget;
use App\Support\Format;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Estimasi biaya per tahun per provider untuk penganggaran (layanan aktif, biaya disetahunkan).
 */
class ProviderCostTable extends TableWidget
{
    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Estimasi biaya per tahun')
            ->description('Per provider, dari layanan aktif')
            ->records(fn (): array => app(HostingBudget::class)->perProvider()->keyBy('provider')->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada layanan aktif')
            ->columns([
                TextColumn::make('provider')
                    ->label('Provider'),
                TextColumn::make('services')
                    ->label('Layanan')
                    ->numeric(),
                TextColumn::make('annual_cost')
                    ->label('Per tahun')
                    ->formatStateUsing(fn ($state): string => Format::rupiah($state))
                    ->fontFamily('mono')
                    ->alignEnd(),
            ]);
    }
}
