<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'trading_account_id',
        'symbol',
        'type',
        'volume',
        'open_price',
        'price',
        'expiry',
        'close_price',
        'open_time',
        'close_time',
        'profit_loss',
        'leverage',
        'status',
        'order_type',
        'stop_loss_price',
        'take_profit_price',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function tradingAccount()
    {
        return $this->belongsTo(\App\Models\TradingAccount::class);
    }
}