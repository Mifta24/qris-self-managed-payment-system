<?php

use App\Http\Controllers\InvoicePageController;
use App\Http\Controllers\QrImageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/invoice/{invoice:invoice_number}', [InvoicePageController::class, 'show'])
    ->name('invoices.show');

Route::get('/invoice/{invoice:invoice_number}/status', [InvoicePageController::class, 'status'])
    ->name('invoices.status');

Route::get('/qris/{invoice:invoice_number}.svg', [QrImageController::class, 'show'])
    ->name('invoices.qr');
