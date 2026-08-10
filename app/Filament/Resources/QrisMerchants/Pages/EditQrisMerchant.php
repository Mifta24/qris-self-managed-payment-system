<?php

declare(strict_types=1);

namespace App\Filament\Resources\QrisMerchants\Pages;

use App\Filament\Resources\QrisMerchants\QrisMerchantResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQrisMerchant extends EditRecord
{
    protected static string $resource = QrisMerchantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
