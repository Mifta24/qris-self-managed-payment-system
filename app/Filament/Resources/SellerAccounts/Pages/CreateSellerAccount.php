<?php

declare(strict_types=1);

namespace App\Filament\Resources\SellerAccounts\Pages;

use App\Filament\Resources\SellerAccounts\SellerAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSellerAccount extends CreateRecord
{
    protected static string $resource = SellerAccountResource::class;
}
