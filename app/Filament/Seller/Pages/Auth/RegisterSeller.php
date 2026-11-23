<?php

declare(strict_types=1);

namespace App\Filament\Seller\Pages\Auth;

use App\Filament\Resources\SellerAccounts\SellerAccountResource;
use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;

/**
 * Pendaftaran mandiri penjual. Akun yang terdaftar BELUM punya toko, jadi
 * belum bisa membuka panel: admin harus menghubungkannya ke toko dulu lewat
 * menu Akun Penjual. Karena itu pendaftar tidak di-login otomatis.
 */
final class RegisterSeller extends Register
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Daftar sebagai penjual';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Akun baru perlu disetujui admin dulu sebelum bisa dipakai.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                Textarea::make('registration_note')
                    ->label('Nama toko dan nomor WhatsApp')
                    ->helperText('Supaya admin tahu toko mana yang Anda maksud dan bisa menghubungi Anda.')
                    ->required()
                    ->maxLength(500)
                    ->rows(3),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->rule(Password::min(10))
            ->helperText('Minimal 10 karakter.');
    }

    public function register(): ?RegistrationResponse
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        if ($this->isRegisterRateLimited($this->data['email'] ?? '')) {
            return null;
        }

        $user = $this->wrapInDatabaseTransaction(function (): Model {
            $data = $this->form->getState();

            return $this->handleRegistration($data);
        });

        $this->notifyAdmins($user);

        Notification::make()
            ->title('Pendaftaran diterima')
            ->body('Admin akan menghubungkan akun Anda ke toko. Anda baru bisa masuk setelah disetujui.')
            ->success()
            ->persistent()
            ->send();

        $this->redirect(Filament::getLoginUrl());

        return null;
    }

    private function notifyAdmins(Model $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        Notification::make()
            ->title('Pendaftaran penjual baru')
            ->body("{$user->name} ({$user->email}) menunggu dihubungkan ke toko.")
            ->actions([
                Action::make('open')
                    ->label('Tinjau')
                    ->url(SellerAccountResource::getUrl('edit', ['record' => $user], panel: 'admin')),
            ])
            ->sendToDatabase(User::query()->where('is_admin', true)->get());
    }
}
