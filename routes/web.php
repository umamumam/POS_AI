<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\POSController;

Route::get('/', [POSController::class, 'index'])->name('home');

// POS Routes (Bisa diakses langsung tanpa login)
Route::get('pos', [POSController::class, 'index'])->name('pos.index');
Route::get('api/products', [POSController::class, 'search'])->name('api.products.search');
Route::post('api/pos/analyze', [POSController::class, 'analyzeImage'])->name('api.pos.analyze');
Route::post('api/pos/analyze-text', [POSController::class, 'analyzeText'])->name('api.pos.analyze_text');
Route::post('api/pos/checkout', [POSController::class, 'checkout'])->name('api.pos.checkout');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
