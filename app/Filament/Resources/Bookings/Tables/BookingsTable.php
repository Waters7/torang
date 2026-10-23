<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Enums\ActivityType;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingActions;
use App\Models\Booking;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('date')->orderBy('start_time'))
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('activity_name')
                    ->label('Kegiatan')
                    ->description(fn (Booking $record): string => collect([
                        $record->department?->short_name,
                        $record->activity_type->label(),
                    ])->filter()->implode(' · '))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('lab.name')
                    ->label('Lab'),
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('D, j M Y')
                    ->sortable(),
                TextColumn::make('time_range')
                    ->label('Jam')
                    ->state(fn (Booking $record): string => $record->timeRange())
                    ->fontFamily('mono'),
                TextColumn::make('participants_count')
                    ->label('Peserta')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('applicant_name')
                    ->label('Pemohon')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('j M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(BookingStatus::class)
                    ->multiple(),
                SelectFilter::make('lab')
                    ->label('Lab')
                    ->relationship('lab', 'name'),
                SelectFilter::make('activity_type')
                    ->label('Jenis kegiatan')
                    ->options(ActivityType::class),
                SelectFilter::make('source')
                    ->label('Sumber')
                    ->options(BookingSource::class),
                Filter::make('date')
                    ->label('Tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal'),
                        DatePicker::make('until')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('date', '<=', $date))),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    BookingActions::approve(),
                    BookingActions::reject(),
                    BookingActions::recordUsage(),
                    BookingActions::downloadAttachment(),
                    EditAction::make(),
                    BookingActions::cancel(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BookingActions::bulkApprove(),
                    BookingActions::bulkReject(),
                ]),
            ]);
    }
}
