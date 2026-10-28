<?php

namespace App\Filament\Resources\Tickets;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\NavigationGroup;
use App\Models\Staff;
use App\Models\Ticket;
use App\Services\Helpdesk\TicketService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Services;

    protected static ?string $modelLabel = 'tiket';

    protected static ?string $pluralModelLabel = 'Tiket';

    protected static ?string $slug = 'tiket';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('reporter_name')->label('Nama pelapor')->default(fn () => auth()->user()->name)->required()->maxLength(255),
            TextInput::make('reporter_contact')->label('Email / WhatsApp')->default(fn () => auth()->user()->email)->required()->maxLength(255),
            Select::make('department_id')->label('Jurusan / unit')->relationship('department', 'name')->searchable()->preload(),
            Select::make('category')->label('Kategori')->options(TicketCategory::class)->required(),
            TextInput::make('title')->label('Judul')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('description')->label('Uraian gangguan')->required()->maxLength(10000)->rows(5)->columnSpanFull(),
            FileUpload::make('attachment')->label('Lampiran PDF')->acceptedFileTypes(['application/pdf'])->maxSize(2048)->storeFiles(false)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Laporan gangguan')->columns(2)->schema([
                TextEntry::make('code')->label('Kode')->copyable(), TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('title')->label('Judul')->columnSpanFull(), TextEntry::make('description')->label('Uraian')->columnSpanFull(),
                TextEntry::make('reporter_name')->label('Pelapor'), TextEntry::make('reporter_contact')->label('Kontak'),
                TextEntry::make('department.name')->label('Jurusan / unit')->placeholder('—'), TextEntry::make('category')->label('Kategori'),
                TextEntry::make('priority')->label('Prioritas')->badge(), TextEntry::make('assignee.name')->label('Petugas')->placeholder('Belum ditugaskan'),
                TextEntry::make('created_at')->label('Diterima')->dateTime('d M Y H:i')->suffix(' WITA'),
            ])->columnSpanFull(),
            Section::make('Penilaian pelapor')->schema([
                TextEntry::make('rating')->label('Nilai (1–5)')->placeholder('Belum dinilai'),
                TextEntry::make('feedback')->label('Masukan')->placeholder('—'),
            ])->columnSpanFull(),
            RepeatableEntry::make('comments')->label('Riwayat komentar')->schema([
                TextEntry::make('user.name')->label('Petugas'), TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->suffix(' WITA'),
                TextEntry::make('is_internal')->label('Jenis')->formatStateUsing(fn (bool $state) => $state ? 'Internal' : 'Untuk pelapor'),
                TextEntry::make('body')->label('Komentar')->columnSpanFull(),
            ])->columns(3)->columnSpanFull(),
        ]);
    }

    public static function actions(): array
    {
        return [
            Action::make('updateTicket')->label('Tindak lanjuti')->visible(fn (Ticket $record) => auth()->user()->can('update', $record))
                ->fillForm(fn (Ticket $record) => ['status' => $record->status->value, 'priority' => $record->priority->value, 'assigned_to' => $record->assigned_to])
                ->schema([
                    Select::make('status')->label('Status')->options(fn (Ticket $record) => collect([$record->status, ...$record->status->next()])->mapWithKeys(fn ($s) => [$s->value => $s->label()]))->required()->live(),
                    Select::make('priority')->label('Prioritas')->options(TicketPriority::class)->required(),
                    Select::make('assigned_to')->label('Petugas')->options(fn () => Staff::where('is_active', true)->pluck('name', 'id'))->searchable()->requiredIf('status', TicketStatus::InProgress->value),
                ])->action(function (Ticket $record, array $data, Action $action) {
                    try {
                        app(TicketService::class)->update($record, $data, auth()->user());
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title('Tiket belum dapat diperbarui')->body(collect($exception->errors())->flatten()->implode(' '))->send();
                        $action->halt();
                    }
                }),
            Action::make('comment')->label('Tambah komentar')->visible(fn (Ticket $record) => auth()->user()->can('update', $record))
                ->schema([Textarea::make('body')->label('Komentar')->required()->maxLength(5000), Toggle::make('is_internal')->label('Catatan internal')->default(true)->helperText('Komentar untuk pelapor hanya terlihat melalui tautan rahasia penilaian.')])
                ->action(fn (Ticket $record, array $data) => app(TicketService::class)->comment($record, $data['body'], $data['is_internal'], auth()->user())),
            Action::make('attachment')->label('Unduh lampiran')->visible(fn (Ticket $record) => filled($record->attachment_path))
                ->url(fn (Ticket $record) => route('admin.tickets.attachment', $record)),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('code')->label('Kode')->searchable(), TextColumn::make('title')->label('Gangguan')->searchable()->wrap(),
            TextColumn::make('category')->label('Kategori'), TextColumn::make('priority')->label('Prioritas')->badge(),
            TextColumn::make('status')->label('Status')->badge(), TextColumn::make('assignee.name')->label('Petugas')->placeholder('—'),
            TextColumn::make('created_at')->label('Diterima')->dateTime('d M Y H:i')->suffix(' WITA'),
        ])->filters([
            SelectFilter::make('status')->label('Status')->options(TicketStatus::class),
            SelectFilter::make('category')->label('Kategori')->options(TicketCategory::class),
            SelectFilter::make('priority')->label('Prioritas')->options(TicketPriority::class),
        ])->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTickets::route('/'), 'create' => Pages\CreateTicket::route('/create'), 'view' => Pages\ViewTicket::route('/{record}')];
    }
}
