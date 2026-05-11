<?php

use App\Models\Voucher;
use App\Service\VoucherPrintPDFService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('filament.main.auth.login');
});

Route::get('/voucher/print/{voucher}', function (Voucher $voucher) {
    $pdfService = new VoucherPrintPDFService($voucher->ticket_number);
    $pdfService->generatePDFContent(); // this will directly echo the PDF
    exit; // stop Laravel from continuing
})->name('voucher.print');
