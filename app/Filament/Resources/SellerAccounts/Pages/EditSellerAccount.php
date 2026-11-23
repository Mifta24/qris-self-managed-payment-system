<?php

declare(strict_types=1);

namespace App\Filament\Resources\SellerAccounts\Pages;

use App\Filament\Resources\SellerAccounts\SellerAccountResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSellerAccount extends EditRecord
{
    protected static string $resource = SellerAccountResource::class;

    /**
     * Menolak pendaftar = menghapus akunnya. Hanya untuk akun yang belum
     * dihubungkan ke toko; penjual aktif tidak bisa dihapus dari sini.
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make('reject')
                ->label('Tolak pendaftaran')
                ->modalHeading('Tolak dan hapus pendaftaran ini?')
                ->visible(fn (User $record): bool => ! $record->is_admin && ! $record->qrisMerchants()->exists()),
        ];
    }
}
