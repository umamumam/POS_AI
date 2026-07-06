<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\POSController;
use App\Http\Controllers\ProdukController;

Route::get('/', [POSController::class, 'index'])->name('home');

// POS Routes (Bisa diakses langsung tanpa login)
Route::get('pos', [POSController::class, 'index'])->name('pos.index');
Route::get('api/products', [POSController::class, 'search'])->name('api.products.search');
Route::post('api/pos/analyze', [POSController::class, 'analyzeImage'])->name('api.pos.analyze');
Route::post('api/pos/analyze-text', [POSController::class, 'analyzeText'])->name('api.pos.analyze_text');
Route::post('api/pos/checkout', [POSController::class, 'checkout'])->name('api.pos.checkout');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [POSController::class, 'dashboard'])->name('dashboard');
    Route::get('transaksi-ai', [POSController::class, 'transaksiAi'])->name('transaksi_ai');
    Route::get('api/transactions/today', [POSController::class, 'getTodayTransactions'])->name('api.transactions.today');
    Route::resource('products-crud', ProdukController::class)->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
