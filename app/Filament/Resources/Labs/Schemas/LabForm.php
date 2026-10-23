<?php

namespace App\Filament\Resources\Labs\Schemas;

use App\Enums\DeviceType;
use App\Enums\LabStatus;
use Closure;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class LabForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Data lab')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode')
                            ->placeholder('LAB-1')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),
                        TextInput::make('name')
                            ->label('Nama')
                            ->placeholder('Lab CBT 1')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('location')
                            ->label('Lokasi / gedung / lantai')
                            ->maxLength(255),
                        TextInput::make('capacity')
                            ->label('Kapasitas ruangan')
                            ->helperText('Jumlah peserta tetap dibatasi PC Client berkondisi baik.')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->suffix('orang'),
                        Select::make('status')
                            ->label('Status')
                            ->options(LabStatus::class)
                            ->default(LabStatus::Active)
                            ->required(),
                        Select::make('pic_user_id')
                            ->label('Penanggung jawab')
                            ->relationship('pic', 'name')
                            ->searchable()
                            ->preload(),
                        ColorPicker::make('color')
                            ->label('Warna kalender')
                            ->default('#04B6A3')
                            ->required()
                            ->regex('/^#[0-9A-Fa-f]{6}$/'),
                        FileUpload::make('photo_path')
                            ->label('Foto')
                            ->image()
                            ->disk('local')
                            ->directory('labs')
                            ->visibility('private')
                            ->maxSize(2048),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->columnSpanFull(),
                    ]),
                Section::make('Jumlah perangkat')
                    ->description('Cukup jumlah dan kondisi per jenis; inventaris detail dikelola unit BMN.')
                    ->schema([
                        Repeater::make('deviceCounts')
                            ->hiddenLabel()
                            ->relationship()
                            ->columns(5)
                            ->addActionLabel('Tambah jenis perangkat')
                            ->defaultItems(0)
                            ->schema([
                                Select::make('device_type')
                                    ->label('Jenis')
                                    ->options(DeviceType::class)
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                TextInput::make('total')
                                    ->label('Total')
                                    ->required()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0),
                                TextInput::make('good')
                                    ->label('Baik')
                                    ->required()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0),
                                TextInput::make('broken')
                                    ->label('Rusak')
                                    ->required()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0)
                                    ->rules([
                                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                            if ((int) $get('good') + (int) $value > (int) $get('total')) {
                                                $fail('Jumlah baik + rusak melebihi total.');
                                            }
                                        },
                                    ]),
                                TextInput::make('notes')
                                    ->label('Catatan')
                                    ->maxLength(255),
                            ]),
                    ]),
            ]);
    }
}
