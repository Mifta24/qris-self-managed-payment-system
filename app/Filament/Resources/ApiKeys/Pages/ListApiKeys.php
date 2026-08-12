<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiKeys\Pages;

use App\Filament\Resources\ApiKeys\ApiKeyResource;
use App\Models\ApiKey;
use App\Support\ApiKeyGenerator;
use App\Support\SigningSecretGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListApiKeys extends ListRecords
{
    protected static string $resource = ApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->createApiKeyAction(),
        ];
    }

    /**
     * API key dan signing_secret digenerate di sini, bukan diketik admin.
     * Keduanya cuma ditampilkan sekali lewat notifikasi persistent setelah
     * dibuat — tidak pernah disimpan plaintext, tidak pernah dicatat log.
     */
    private function createApiKeyAction(): Action
    {
        return Action::make('createApiKey')
            ->label('Buat API Key')
            ->schema([
                Select::make('project_id')
                    ->relationship('project', 'name')
                    ->required()
                    ->helperText('Project pemilik key ini. Key hanya berlaku untuk memanggil API atas nama project yang dipilih.'),
                TextInput::make('name')
                    ->required()
                    ->helperText('Label bebas untuk membedakan key ini, misalnya nama environment atau integrasi yang memakainya.'),
                DateTimePicker::make('expires_at')
                    ->helperText('Setelah waktu ini, key otomatis ditolak di setiap permintaan API. Kosongkan untuk tidak pernah kedaluwarsa.'),
                TagsInput::make('allowed_ips')
                    ->label('IP yang diizinkan')
                    ->helperText('Kosongkan untuk mengizinkan semua IP. Catatan: pembatasan IP ini belum aktif diterapkan di sisi server, tersimpan tapi belum memfilter permintaan API.'),
            ])
            ->action(function (array $data): void {
                $generatedKey = (new ApiKeyGenerator)->generate();
                $signingSecret = (new SigningSecretGenerator)->generate();

                ApiKey::create([
                    'project_id' => $data['project_id'],
                    'name' => $data['name'],
                    'key_prefix' => $generatedKey['prefix'],
                    'key_hash' => $generatedKey['hash'],
                    'signing_secret' => $signingSecret,
                    'expires_at' => $data['expires_at'] ?? null,
                    'allowed_ips' => $data['allowed_ips'] ?? null,
                ]);

                Notification::make()
                    ->title('API Key dibuat')
                    ->success()
                    ->persistent()
                    ->body(
                        "Simpan sekarang — tidak akan ditampilkan lagi.\n\n".
                        "X-Api-Key: {$generatedKey['key']}\n".
                        "Signing secret: {$signingSecret}"
                    )
                    ->send();
            });
    }
}
