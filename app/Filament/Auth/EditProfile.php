<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Rules\StaffPassword;
use App\Support\RequestContext;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Profil & ganti kata sandi petugas. Akun dengan kata sandi awal (NIP) diarahkan ke sini oleh
 * EnsurePasswordChanged dan baru bisa memakai panel setelah menggantinya.
 */
class EditProfile extends BaseEditProfile
{
    private bool $completedForcedChange = false;

    public function getTitle(): string|Htmlable
    {
        return $this->mustChangePassword() ? 'Ganti kata sandi' : 'Profil & kata sandi';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->mustChangePassword()
            ? 'Kata sandi Anda masih kata sandi awal (NIP). Ganti dulu sebelum memakai TORANG.'
            : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent()->disabled()->dehydrated(false),
            TextInput::make('nip')->label('NIP')->disabled()->dehydrated(false)->visible(fn (): bool => filled($this->user()->nip)),
            $this->getEmailFormComponent()->disabled()->dehydrated(false),
            $this->getPasswordFormComponent()
                ->label('Kata sandi baru')
                ->helperText('Minimal '.StaffPassword::MIN_LENGTH.' karakter dan tidak memuat NIP. Kosongkan bila tidak ingin mengganti.')
                ->required(fn (): bool => $this->mustChangePassword())
                ->rule(fn (): StaffPassword => new StaffPassword($this->user()->nip)),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        if (blank($data['password'] ?? null)) {
            return $record;
        }

        $this->completedForcedChange = (bool) $record->must_change_password;

        $record = parent::handleRecordUpdate($record, [...$data, 'must_change_password' => false]);

        activity('auth')
            ->causedBy($record)
            ->performedOn($record)
            ->event('password_changed')
            ->withProperties(RequestContext::forActivity())
            ->log('Kata sandi diganti dari halaman profil');

        return $record;
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->completedForcedChange ? Filament::getUrl() : parent::getRedirectUrl();
    }

    private function mustChangePassword(): bool
    {
        return (bool) $this->user()->must_change_password;
    }

    private function user(): User
    {
        /** @var User */
        return $this->getUser();
    }
}
