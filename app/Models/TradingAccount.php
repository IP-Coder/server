<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingAccount extends Model
{
    protected $fillable = [
        'user_id',
        'account_currency',
        'balance',
        'equity',
        'used_margin',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function orders()
    {
        return $this->hasMany(\App\Models\Order::class);
    }
}