<?php

namespace App\Filament\Resources\Announcements;

use App\Enums\AnnouncementType;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use App\Models\Announcement;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Pengumuman & maintenance terjadwal (SPEC §8 no. 2). Yang publik tampil sebagai banner beranda selama aktif.
 */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Services;

    protected static ?string $modelLabel = 'pengumuman';

    protected static ?string $pluralModelLabel = 'Pengumuman';

    protected static ?string $slug = 'pengumuman';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Judul')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('body')
                ->label('Isi pengumuman')
                ->helperText('Isi ini ditampilkan kepada publik jika diaktifkan. Jangan sertakan data pribadi atau kredensial.')
                ->required()
                ->maxLength(5000)
                ->rows(5)
                ->columnSpanFull(),
            Select::make('type')
                ->label('Tipe')
                ->options(AnnouncementType::class)
                ->default(AnnouncementType::Info)
                ->required(),
            Toggle::make('is_public')
                ->label('Tampilkan di beranda')
                ->default(false),
            DateTimePicker::make('starts_at')
                ->label('Mulai (WITA)')
                ->seconds(false)
                ->default(now())
                ->required(),
            DateTimePicker::make('ends_at')
                ->label('Selesai (WITA)')
                ->seconds(false)
                ->after('starts_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->wrap(),
                TextColumn::make('type')->label('Tipe')->badge(),
                TextColumn::make('starts_at')->label('Mulai')->dateTime('d M Y H:i'),
                TextColumn::make('ends_at')->label('Selesai')->dateTime('d M Y H:i')->placeholder('Tanpa batas'),
                IconColumn::make('is_public')->label('Publik')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAnnouncements::route('/'),
        ];
    }
}
