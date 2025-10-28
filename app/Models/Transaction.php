<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'agent_code',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // ⬇️ ADD THIS
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}