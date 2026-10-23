<?php

namespace App\Filament\Resources\Staff;

use App\Filament\NavigationGroup;
use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Models\Staff;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class StaffResource extends Resource
{
    protected static ?string $model = Staff::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Staff;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Petugas';

    protected static ?string $modelLabel = 'petugas';

    protected static ?string $pluralModelLabel = 'petugas';

    protected static ?string $slug = 'petugas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('nip')
                    ->label('NIP')
                    ->maxLength(30),
                TextInput::make('position')
                    ->label('Jabatan')
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('No. WhatsApp')
                    ->tel()
                    ->maxLength(30),
                TextInput::make('telegram_chat_id')
                    ->label('Telegram chat ID')
                    ->helperText('Petugas perlu memulai chat dengan bot TORANG agar bisa menerima notifikasi jadwal.')
                    ->regex('/^-?[0-9]+$/')
                    ->maxLength(50),
                Select::make('user_id')
                    ->label('Akun pengguna')
                    ->helperText('Opsional. Hubungkan agar petugas bisa membuka menu Jadwal Saya.')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->description(fn (Staff $record): ?string => $record->position)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nip')
                    ->label('NIP')
                    ->fontFamily('mono')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('WhatsApp')
                    ->fontFamily('mono')
                    ->placeholder('-'),
                IconColumn::make('telegram')
                    ->label('Telegram')
                    ->state(fn (Staff $record): bool => filled($record->telegram_chat_id))
                    ->boolean(),
                TextColumn::make('user.email')
                    ->label('Akun')
                    ->placeholder('Tidak terhubung'),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStaff::route('/'),
        ];
    }
}
