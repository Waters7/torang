<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\NavigationGroup;
use App\Services\System\UserAdministration;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role as RoleModel;
use UnitEnum;

class ManageRoles extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?string $title = 'Role & hak akses';

    protected static ?string $slug = 'role';

    public static function canAccess(): bool
    {
        return auth()->user()?->is_active && auth()->user()->can(Permission::RolesManage->value);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table->query(RoleModel::query()->where('guard_name', 'web')->whereIn('name', Role::values())->with('permissions'))
            ->columns([TextColumn::make('name')->label('Role')->formatStateUsing(fn ($state) => Role::from($state)->label()), TextColumn::make('permissions_count')->counts('permissions')->label('Jumlah izin')])
            ->recordActions([Action::make('permissions')->label('Atur hak akses')->visible(fn ($record) => $record->name !== Role::SuperAdmin->value)
                ->fillForm(fn ($record) => ['permissions' => $record->permissions->pluck('name')->all()])
                ->schema([CheckboxList::make('permissions')->label('Izin akses')->options(array_combine(Permission::values(), Permission::values()))->columns(2)])
                ->action(fn ($record, array $data) => app(UserAdministration::class)->permissions($record, $data['permissions'] ?? [], auth()->user()))]);
    }
}
