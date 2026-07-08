<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\POSController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WaNotificationController;

Route::get('/', [POSController::class, 'index'])->name('home');

// Webhook Fonnte (tidak perlu auth, dikecualikan dari CSRF di bootstrap/app.php)
Route::post('webhooks/fonnte', [WebhookController::class, 'handleFonnte'])->name('webhooks.fonnte');

// Temporary Debug Route for WhatsApp
Route::get('debug-wa', function () {
    $logPath = storage_path('logs/laravel.log');
    $logs = 'Log file not found.';
    if (file_exists($logPath)) {
        $logContent = file_get_contents($logPath);
        $logs = strlen($logContent) > 3000 ? substr($logContent, -3000) : $logContent;
    }
    
    return response()->json([
        'config' => \App\Models\WaConfig::first(),
        'notifications' => \App\Models\WaNotification::latest()->take(10)->get(),
        'logs' => $logs,
    ]);
});

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
    Route::put('api/transactions/{transaction}', [POSController::class, 'updateTransaction'])->name('api.transactions.update');
    Route::resource('products-crud', ProdukController::class)->only(['index', 'store', 'update', 'destroy']);
    
    // WhatsApp Notifications API
    Route::get('api/wa-notifications/unread', [WaNotificationController::class, 'unread'])->name('api.wa_notifications.unread');
    Route::post('api/wa-notifications/mark-read', [WaNotificationController::class, 'markRead'])->name('api.wa_notifications.mark_read');
});

require __DIR__.'/settings.php';
