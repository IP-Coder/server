<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TransactionController;

// Public

// Protected
// Route::get('v1/markets',[MarketController::class, 'index']);
// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('logout', [AuthController::class, 'logout']);
//     Route::get('v1/markets/{symbol}', [MarketController::class, 'show']);
//     Route::get('v1/orders',          [OrderController::class, 'index']);
//     Route::post('v1/orders',          [OrderController::class, 'store']);
//     Route::get('v1/orders/{id}',     [OrderController::class, 'show']);
//     Route::delete('v1/orders/{id}',     [OrderController::class, 'destroy']);
//     Route::get('v1/trades', [TradeController::class, 'index']);
// });
Route::post('/signup', [AuthController::class, 'signup']);
Route::post('/login',  [AuthController::class, 'login']);
Route::get('/login',  [AuthController::class, 'login']);
Route::get('/ohlc', [OrderController::class, 'ohlc']); // or a new controller if you prefer
Route::get('/symbols', [OrderController::class, 'symbols']);

Route::get('/convert', [OrderController::class, 'convert']);

// Route::get('/quote', [QuoteController::class, 'getQuote']);
Route::middleware('auth:sanctum')->group(function () {
    Route::patch('/order/{order}/sl-tp', [OrderController::class, 'updateSlTp']);
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{symbol}', [FavoriteController::class, 'destroy']);
    Route::get('/account', [OrderController::class, 'account']);
    Route::post('/place', [OrderController::class, 'placeOrder']);
    Route::get('/orders', [OrderController::class, 'orders']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/order/close', [OrderController::class, 'closeOrder']);
    // Profile
    Route::get('/me',                 [UserController::class, 'me']);
    Route::post('/user/update',       [UserController::class, 'update']);
    Route::post('/user/change-password', [UserController::class, 'changePassword']);

    // Transactions
    Route::post('/transactions/create', [TransactionController::class, 'store']);
    Route::get('/transactions/my',     [TransactionController::class, 'my']);
});
Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {
    Route::get('/users', [AdminController::class, 'listUsers']);
    Route::get('/users/{id}/account', [AdminController::class, 'userAccount']);
    Route::get('/users', [AdminController::class, 'listUsers']);
    Route::get('/users/{id}/trades', [AdminController::class, 'userTrades']);
    Route::get('/users/{id}/transactions', [TransactionController::class, 'userTransactions']); // ✅ add this
    Route::post('/trades/{id}/close', [AdminController::class, 'closeTrade']);
    Route::post('/transactions/{id}/status', [TransactionController::class, 'updateStatus']);
});