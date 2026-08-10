<?php

declare(strict_types=1);

namespace App\Filament\Resources\QrisMerchants\Pages;

use App\Filament\Resources\QrisMerchants\QrisMerchantResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQrisMerchant extends CreateRecord
{
    protected static string $resource = QrisMerchantResource::class;
}
