<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id',
        'ticket_no',
        'name',
        'email',
        'phone_code',
        'phone',
        'subject',
        'message',
        'attachments',
        'source',
        'status',
    ];

    protected $casts = [
        'attachments' => 'array', // stores JSON array
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
