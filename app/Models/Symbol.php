<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Symbol extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'title',
        'decimals',
        'base_symbol',
        'base_title',
        'quote_symbol',
        'quote_title',
        'created_at',
        'updated_at'
    ];
}