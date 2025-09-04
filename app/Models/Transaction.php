<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'currency',
        'chain',
        'address',
        'receipt_path',
        'method',
        'comment',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];
}