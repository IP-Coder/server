<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketPriceUpdate implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $symbol;
    public float $price;
    public float $volume;
    public float $change;
    public float $changePercent;

    public function __construct(
        string $symbol,
        float $price,
        float $volume,
        float $change,
        float $changePercent,
    ) {
        $this->symbol        = $symbol;
        $this->price         = $price;
        $this->volume        = $volume;
        $this->change        = $change;
        $this->changePercent = $changePercent;
    }

    public function broadcastOn(): Channel
    {
        $safeSymbol = str_replace(':', '_', $this->symbol);
        return new Channel("market.price.{$safeSymbol}");
    }

    public function broadcastAs(): string
    {
        return 'price.update';
    }

    public function broadcastWith(): array
    {
        return [
            'symbol'         => $this->symbol,
            'last_price'     => $this->price,
            'volume'         => $this->volume,
            'change'         => $this->change,
            'change_percent' => $this->changePercent,
        ];
    }
}
