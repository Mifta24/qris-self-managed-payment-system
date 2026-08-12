<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('api.signature')
    ->group(function (): void {
        Route::post('/invoices', [InvoiceController::class, 'store'])
            ->middleware('throttle:api-write');

        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])
            ->middleware('throttle:api-read');

        Route::get('/invoices/{invoice}/status', [InvoiceController::class, 'status'])
            ->middleware('throttle:api-read');

        Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])
            ->middleware('throttle:api-write');

        Route::get('/transactions', [InvoiceController::class, 'transactions'])
            ->middleware('throttle:api-read');
    });
