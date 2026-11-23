<?php

declare(strict_types=1);

namespace App\Filament\Resources\SellerAccounts;

use App\Filament\Resources\SellerAccounts\Pages\CreateSellerAccount;
use App\Filament\Resources\SellerAccounts\Pages\EditSellerAccount;
use App\Filament\Resources\SellerAccounts\Pages\ListSellerAccounts;
use App\Filament\Resources\SellerAccounts\Schemas\SellerAccountForm;
use App\Filament\Resources\SellerAccounts\Tables\SellerAccountsTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Akun penjual untuk panel /toko. Hanya menampilkan user non-admin — akun
 * admin tidak dibuat atau diubah dari sini, supaya tidak ada jalur
 * menaikkan hak akses lewat form.
 */
class SellerAccountResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'seller-accounts';

    protected static ?string $modelLabel = 'akun penjual';

    protected static ?string $pluralModelLabel = 'akun penjual';

    protected static ?string $navigationLabel = 'Akun Penjual';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    /**
     * Jumlah pendaftar yang belum dihubungkan ke toko.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->whereDoesntHave('qrisMerchants')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_admin', false);
    }

    public static function form(Schema $schema): Schema
    {
        return SellerAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SellerAccountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSellerAccounts::route('/'),
            'create' => CreateSellerAccount::route('/create'),
            'edit' => EditSellerAccount::route('/{record}/edit'),
        ];
    }
}
