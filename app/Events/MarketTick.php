<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketTick implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $symbol;
    public float  $ask;
    public float  $bid;
    public float  $askSize;
    public float  $bidSize;


    public function __construct(
        string $symbol,
        float  $ask,
        float  $bid,
        float  $askSize,
        float  $bidSize,

    ) {
        $this->symbol    = $symbol;
        $this->ask       = $ask;
        $this->bid       = $bid;
        $this->askSize   = $askSize;
        $this->bidSize   = $bidSize;
    }

    public function broadcastOn(): Channel
    {
        $safeSymbol = str_replace(':', '_', $this->symbol);
        return new Channel("market.tick.{$safeSymbol}");
    }

    public function broadcastAs(): string
    {
        return 'tick.update';
    }

    public function broadcastWith(): array
    {
        return [
            'symbol'    => $this->symbol,
            'ask'       => $this->ask,
            'bid'       => $this->bid,
            'ask_size'  => $this->askSize,
            'bid_size'  => $this->bidSize,
        ];
    }
}