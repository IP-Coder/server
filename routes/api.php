<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\KycController;

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
Route::get('/ohlc', [OrderController::class, 'ohlc']);
Route::get('/symbols', [OrderController::class, 'symbols']);
Route::get('/convert', [OrderController::class, 'convert']);

// Route::get('/quote', [QuoteController::class, 'getQuote']);
Route::middleware('auth:sanctum')->group(function () {
    //OrderController
    Route::patch('/order/{order}/sl-tp', [OrderController::class, 'updateSlTp']);
    Route::get('/account', [OrderController::class, 'account']);
    Route::post('/place', [OrderController::class, 'placeOrder']);
    Route::get('/orders', [OrderController::class, 'orders']);
    Route::post('/order/close', [OrderController::class, 'closeOrder']);
    //AuthController
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{symbol}', [FavoriteController::class, 'destroy']);
    //SupportController
    Route::post('/support/tickets', [SupportController::class, 'store']);
    Route::get('/support/tickets/my', [SupportController::class, 'my']);
    // Profile
    Route::get('/me',                 [UserController::class, 'me']);
    Route::post('/user/update',       [UserController::class, 'update']);
    Route::post('/user/change-password', [UserController::class, 'changePassword']);
    // Transactions
    Route::post('/transactions/create', [TransactionController::class, 'store']);
    Route::get('/transactions/my',     [TransactionController::class, 'my']);
    //referral system
    Route::get('/refer/my', [ReferralController::class, 'my']);
    Route::get('/refer/history', [ReferralController::class, 'history']);
    Route::post('/user/switch-to-live', [UserController::class, 'switchToLive']);
    // KYC
    Route::get('/kyc/my', [KycController::class, 'my']);
    Route::post('/kyc/submit', [KycController::class, 'submit']);
});
Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {
    Route::get('/users', [AdminController::class, 'listUsers']);
    Route::get('/users/{id}/account', [AdminController::class, 'userAccount']);
    Route::get('/users', [AdminController::class, 'listUsers']);
    Route::get('/users/{id}/trades', [AdminController::class, 'userTrades']);
    Route::post('/trades/{id}/close', [AdminController::class, 'closeTrade']);
    // Route::get('/users/{id}/transactions', [TransactionController::class, 'admintransections']);
    // Route::post('/transactions/{id}/status', [TransactionController::class, 'updateStatus']);
    Route::get('/users/{id}/transactions', [AdminController::class, 'userTransactions']);
    Route::post('/transactions/{id}/status', [AdminController::class, 'updateTransactionStatus']);
});