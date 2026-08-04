<?php

use App\Http\Controllers\InvoicePdfController;
use App\Support\FilamentLogin;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect(Filament::getPanel('market')->getUrl());
    }

    return redirect(FilamentLogin::url(request()));
});

Route::redirect('/login', '/market/login')->name('login');

Route::middleware('auth')->group(function (): void {
    Route::get('/pdf/purchase-invoice/{purchaseInvoice}', [InvoicePdfController::class, 'purchase'])
        ->name('pdf.purchase-invoice');
    Route::get('/pdf/sales-invoice/{salesInvoice}', [InvoicePdfController::class, 'sales'])
        ->name('pdf.sales-invoice');
    Route::get('/pdf/sales-offer-invoice/{salesOfferInvoice}', [InvoicePdfController::class, 'salesOffer'])
        ->name('pdf.sales-offer-invoice');
});
