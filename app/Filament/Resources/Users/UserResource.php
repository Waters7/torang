<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\NavigationGroup;
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
            TextInput::make('name')->label('Nama')->required()->maxLength(255), TextInput::make('email')->label('Email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('password')->label('Password baru')->password()->autocomplete('new-password')->afterStateHydrated(fn ($component) => $component->state(null))->minLength(12)->required(fn (string $operation) => $operation === 'create')->helperText('Minimal 12 karakter. Kosongkan saat edit untuk mempertahankan password.'),
            Select::make('role_names')->label('Role')->multiple()->options(collect(Role::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()]))->required(),
            TextInput::make('phone')->label('WhatsApp')->maxLength(30), TextInput::make('telegram_chat_id')->label('Telegram chat ID')->maxLength(50),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label('Nama')->searchable(), TextColumn::make('email')->label('Email')->searchable(), TextColumn::make('roles.name')->label('Role')->badge(), IconColumn::make('is_active')->label('Aktif')->boolean()])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListUsers::route('/'), 'create' => Pages\CreateUser::route('/create'), 'edit' => Pages\EditUser::route('/{record}/edit')];
    }
}
