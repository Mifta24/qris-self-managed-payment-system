<?php

declare(strict_types=1);

namespace App\Filament\Resources\QrisMerchants\Pages;

use App\Filament\Resources\QrisMerchants\QrisMerchantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQrisMerchants extends ListRecords
{
    protected static string $resource = QrisMerchantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
