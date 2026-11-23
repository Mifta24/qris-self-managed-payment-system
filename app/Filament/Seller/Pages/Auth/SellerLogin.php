<?php

declare(strict_types=1);

namespace App\Filament\Seller\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

/**
 * Sama seperti login bawaan, tetapi penjual yang password-nya benar dan
 * belum dihubungkan ke toko mendapat pesan "menunggu persetujuan", bukan
 * pesan "kredensial salah" yang membingungkan. Pesan ini hanya muncul
 * setelah password terbukti benar.
 */
final class SellerLogin extends Login
{
    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        $allowed = parent::isUserAllowedToAccessPanel($user);

        if (! $allowed && $user instanceof User && ! $user->is_admin && ! $user->qrisMerchants()->exists()) {
            throw ValidationException::withMessages([
                'data.email' => 'Akun Anda belum disetujui admin. Tunggu sampai admin menghubungkannya ke toko Anda.',
            ]);
        }

        return $allowed;
    }
}
