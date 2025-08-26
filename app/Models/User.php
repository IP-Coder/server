<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // ← Add this

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable; // ← Add HasApiTokens here

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    // In app/Models/User.php, inside the User class:
    protected static function booted()
    {
        static::created(function ($user) {
            $user->tradingAccount()->create([
                'account_currency' => 'USD',
                'balance' => 50000.00,
                'equity' => 50000.00,
                'used_margin' => 0.00,
            ]);
        });
    }

    public function tradingAccount()
    {
        return $this->hasOne(\App\Models\TradingAccount::class, 'user_id');
    }

    public function orders()
    {
        return $this->hasMany(\App\Models\Order::class);
    }
}