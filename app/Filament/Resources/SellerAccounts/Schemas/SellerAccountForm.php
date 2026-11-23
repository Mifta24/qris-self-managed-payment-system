<?php

declare(strict_types=1);

namespace App\Filament\Resources\SellerAccounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SellerAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email (untuk login)')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->minLength(10)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Saat mengedit, kosongkan kalau password tidak diganti. Minimal 10 karakter.'),
                Select::make('qrisMerchants')
                    ->label('Toko yang boleh dilihat')
                    ->relationship('qrisMerchants', 'label')
                    ->multiple()
                    ->preload()
                    ->required()
                    ->helperText('Penjual hanya bisa melihat dan mengonfirmasi pembayaran untuk toko yang dipilih di sini.'),
            ]);
    }
}
