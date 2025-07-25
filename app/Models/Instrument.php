<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Instrument extends Model
{
    // Example if you want order <-> instrument relation in future
    public function orders()
    {
        return $this->hasMany(\App\Models\Order::class, 'symbol', 'symbol');
    }
}