<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\SellerPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    SellerPanelProvider::class,
];
