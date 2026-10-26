<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('orders.index'));

// Staff sign in through the shop's single sign-on, which is not part of this demo.
Route::get('/login', fn () => response('Staff sign-in', 401))->name('login');

Route::middleware('auth')->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/export', [OrderController::class, 'export'])->name('orders.export');
    Route::post('/orders/{order}/ship', [OrderController::class, 'ship'])->name('orders.ship');
    Route::get('/reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
});
