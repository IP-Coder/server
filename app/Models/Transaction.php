<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'status',
        'currency',
        'chain',
        'address',
        'receipt_path',
        'method',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];
}