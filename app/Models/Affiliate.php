<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    protected $fillable = ['user_id', 'referred_user_id', 'reward_amount'];

    public function referrer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function referred()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
