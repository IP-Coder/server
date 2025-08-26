<?php

// app/Events/AccountUpdated.php

namespace App\Events;

use App\Models\TradingAccount;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class AccountUpdated implements ShouldBroadcastNow
{
    public TradingAccount $account;

    public function __construct(TradingAccount $account)
    {
        $this->account = $account->refresh(); // fresh data
    }

    public function broadcastOn()
    {
        // private channel scoped to this user
        return new Channel("account.{$this->account->user_id}");
    }

    public function broadcastWith()
    {
        return $this->account->only([
            'balance',
            'equity',
            'used_margin',
            'unrealized_profit',
            'credit'
        ]);
    }
}