<?php

declare(strict_types=1);

namespace App\Filament\Resources\SellerAccounts\Pages;

use App\Filament\Resources\SellerAccounts\SellerAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSellerAccounts extends ListRecords
{
    protected static string $resource = SellerAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
