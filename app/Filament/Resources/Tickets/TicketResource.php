<?php

namespace App\Filament\Resources\Tickets;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Ticket;
use App\Support\Format;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Tiket helpdesk (SPEC §8 no. 1). Tiket dibuat dari panel, form publik /lapor, atau Log Pemakaian;
 * tindak lanjut lewat TicketActions (status sesuai alur, penugasan petugas, komentar).
 */
class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Services;

    protected static ?string $modelLabel = 'tiket';

    protected static ?string $pluralModelLabel = 'Tiket';

    protected static ?string $slug = 'tiket';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('reporter_name')
                ->label('Nama pelapor')
                ->default(fn (): string => auth()->user()->name)
                ->required()
                ->maxLength(255),
            TextInput::make('reporter_contact')
                ->label('Email / WhatsApp')
                ->default(fn (): string => auth()->user()->email)
                ->required()
                ->maxLength(255),
            Select::make('department_id')
                ->label('Jurusan / unit')
                ->relationship('department', 'name')
                ->searchable()
                ->preload(),
            Select::make('category')
                ->label('Kategori')
                ->options(TicketCategory::class)
                ->required(),
            TextInput::make('title')
                ->label('Judul')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('description')
                ->label('Uraian gangguan')
                ->required()
                ->maxLength(10000)
                ->rows(5)
                ->columnSpanFull(),
            FileUpload::make('attachment')
                ->label('Lampiran PDF')
                ->acceptedFileTypes(['application/pdf'])
                ->maxSize(2048)
                ->storeFiles(false)
                ->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Laporan gangguan')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('code')->label('Kode')->copyable(),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('title')->label('Judul')->columnSpanFull(),
                    TextEntry::make('description')->label('Uraian')->columnSpanFull(),
                    TextEntry::make('reporter_name')->label('Pelapor'),
                    TextEntry::make('reporter_contact')->label('Kontak'),
                    TextEntry::make('department.name')->label('Jurusan / unit')->placeholder('—'),
                    TextEntry::make('category')->label('Kategori'),
                    TextEntry::make('priority')->label('Prioritas')->badge(),
                    TextEntry::make('assignee.name')->label('Petugas')->placeholder('Belum ditugaskan'),
                    TextEntry::make('created_at')->label('Diterima')->dateTime('d M Y H:i')->suffix(' WITA'),
                    TextEntry::make('first_responded_at')
                        ->label('Waktu tanggap')
                        ->state(fn (Ticket $record): ?string => $record->first_responded_at ? Format::duration($record->responseMinutes()) : null)
                        ->placeholder('Belum ditindaklanjuti'),
                    TextEntry::make('resolution_notes')
                        ->label('Pekerjaan yang dilakukan')
                        ->placeholder('Diisi saat tiket diselesaikan')
                        ->columnSpanFull(),
                ]),
            Section::make('Penilaian pelapor')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('rating')->label('Nilai (1–5)')->placeholder('Belum dinilai'),
                    TextEntry::make('feedback')->label('Masukan')->placeholder('—'),
                ]),
            RepeatableEntry::make('progressEntries')
                ->label('Rencana & perkembangan penanganan')
                ->state(fn (Ticket $record) => $record->progressEntries()->with('user:id,name')->get())
                ->placeholder('Belum ada catatan penanganan.')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('type')->label('Jenis')->badge(),
                    TextEntry::make('user.name')->label('Dicatat oleh')->placeholder('Tim IT'),
                    TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->suffix(' WITA'),
                    TextEntry::make('status')->label('Status saat dicatat')->badge(),
                    TextEntry::make('is_internal')->label('Dapat dilihat oleh')
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Internal tim IT' : 'Semua pemegang nomor tiket'),
                    TextEntry::make('body')->label('Catatan')->columnSpanFull(),
                ]),
            RepeatableEntry::make('comments')
                ->label('Riwayat komentar')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('user.name')->label('Petugas'),
                    TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->suffix(' WITA'),
                    TextEntry::make('is_internal')
                        ->label('Jenis')
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Internal' : 'Untuk pelapor'),
                    TextEntry::make('body')->label('Komentar')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable(),
                TextColumn::make('title')->label('Gangguan')->searchable()->wrap(),
                TextColumn::make('category')->label('Kategori'),
                TextColumn::make('priority')->label('Prioritas')->badge(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('assignee.name')->label('Petugas')->placeholder('—'),
                TextColumn::make('created_at')->label('Diterima')->dateTime('d M Y H:i')->suffix(' WITA'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(TicketStatus::class),
                SelectFilter::make('category')->label('Kategori')->options(TicketCategory::class),
                SelectFilter::make('priority')->label('Prioritas')->options(TicketPriority::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'create' => CreateTicket::route('/create'),
            'view' => ViewTicket::route('/{record}'),
        ];
    }
}
