<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Akun pengguna panel dan role-nya. Penyimpanan lewat App\Services\System\UserAdministration
 * (melindungi Super Admin terakhir dan akun sendiri).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?string $slug = 'pengguna';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('nip')
                ->label('NIP')
                ->helperText('Opsional. Petugas bisa masuk memakai NIP atau email.')
                ->maxLength(30)
                ->regex('/^\d{9,30}$/')
                ->unique(ignoreRecord: true),
            // Tidak pernah diisi dari database.
            TextInput::make('password')
                ->label('Password baru')
                ->password()
                ->autocomplete('new-password')
                ->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                ->minLength(12)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->helperText('Minimal 12 karakter. Kosongkan saat edit untuk mempertahankan password.'),
            Select::make('role_names')
                ->label('Role')
                ->multiple()
                ->options(collect(Role::cases())->mapWithKeys(fn (Role $role): array => [$role->value => $role->label()]))
                ->required(),
            TextInput::make('phone')
                ->label('WhatsApp')
                ->maxLength(30),
            TextInput::make('telegram_chat_id')
                ->label('Telegram chat ID')
                ->maxLength(50),
            Toggle::make('must_change_password')
                ->label('Wajib ganti kata sandi saat login berikutnya'),
            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('nip')->label('NIP')->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('roles.name')->label('Role')->badge(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
