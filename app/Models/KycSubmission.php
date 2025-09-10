<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'aadhaar_hash',
        'aadhaar_last4',
        'document_path',
        'selfie_path',
        'status',
        'review_notes'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
