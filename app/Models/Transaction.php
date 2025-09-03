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
        'bank_name',
        'beneficiary_city',
        'beneficiary_name',
        'bank_address',
        'account_iban',
        'country',
        'routing_number',
        'swift',
        'comment',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];
}