<?php

namespace App\Filament\Resources\Providers;

use App\Filament\NavigationGroup;
use App\Filament\Resources\Providers\Pages\ManageProviders;
use App\Models\Provider;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Infrastructure;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Provider';

    protected static ?string $modelLabel = 'provider';

    protected static ?string $pluralModelLabel = 'provider';

    protected static ?string $slug = 'provider';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->placeholder('Domainesia')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('website')
                    ->label('Website')
                    ->url()
                    ->placeholder('https://www.domainesia.com')
                    ->maxLength(255),
                TextInput::make('panel_url')
                    ->label('URL panel/member area')
                    ->url()
                    ->maxLength(2048),
                TextInput::make('support_contact')
                    ->label('Kontak support')
                    ->placeholder('Live chat, tiket, atau nomor WA support')
                    ->maxLength(255),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->withCount('services'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->weight('semibold')
                    ->description(fn (Provider $record): ?string => $record->website)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('services_count')
                    ->label('Layanan')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('support_contact')
                    ->label('Kontak support')
                    ->placeholder('-'),
            ])
            ->recordActions([
                Action::make('openPanel')
                    ->label('Buka panel')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Provider $record): ?string => $record->panel_url, shouldOpenInNewTab: true)
                    ->visible(fn (Provider $record): bool => filled($record->panel_url)),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProviders::route('/'),
        ];
    }
}
