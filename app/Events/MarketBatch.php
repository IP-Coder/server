<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketData implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The entire payload from your upstream WebSocket.
     *
     * May contain:
     *  - code, ask, bid, ask_size, bid_size
     *  - last_price, volume, change, change_percent
     *  - series (array of { time, value } points)
     *
     * @var array
     */
    public array $payload;

    /**
     * Create a new event instance.
     *
     * @param  array  $payload
     * @return void
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * We normalize e.g. “NASDAQ:AAPL” → “market.NASDAQ_AAPL”
     *
     * @return \Illuminate\Broadcasting\Channel
     */
    public function broadcastOn(): Channel
    {
        $symbol = $this->payload['code'] ?? 'UNKNOWN';
        $safe   = str_replace(':', '_', $symbol);

        return new Channel("market.{$safe}");
    }

    /**
     * The event’s name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'market.data';
    }

    /**
     * Prepare the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
