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

    protected $table = 'support_tickets';
    protected $guarded = [];
    protected $casts = [
        'attachments' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}