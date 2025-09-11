<?php

// app/Jobs/ProcessOrder.php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Instrument;
use App\Events\AccountUpdated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected int $orderId;
    protected array $data;

    public function __construct(int $orderId, array $data)
    {
        $this->orderId = $orderId;
        $this->data    = $data;
    }

    public function handle()
    {
        DB::transaction(function () {
            /** @var Order $order */
            $order = Order::lockForUpdate()->findOrFail($this->orderId);
            $acct  = $order->tradingAccount;
            $d     = $this->data;

            $instrument    = Instrument::where('symbol', $d['symbol'])->first();
            $contractSize  = (float) ($instrument?->contract_size ?? 100000);
            $volume        = (float) $d['volume'];
            $leverage      = max(1, (int) $d['leverage']);

            if ($d['order_type'] === 'market') {
                // Use front-end sent open_price for execution; fall back to server pre-check if needed
                $price = isset($d['open_price']) && $d['open_price'] > 0
                    ? (float) $d['open_price']
                    : (float) ($d['preflight_price'] ?? 0);

                if ($price <= 0) {
                    $order->update([
                        'status'  => 'failed',
                        'message' => 'Missing execution price',
                    ]);
                    broadcast(new AccountUpdated($acct->refresh()));
                    return;
                }

                // Margin required = price * volume * contract_size / leverage
                $marginRequired = round(($price * $volume * $contractSize) / $leverage, 2);

                // Compute free margin (prefer equity if tracked)
                $equity     = (float) ($acct->equity ?? ($acct->balance ?? 0));
                $usedMargin = (float) ($acct->used_margin ?? 0);
                $freeMargin = max(0, $equity - $usedMargin);

                if ($marginRequired > $freeMargin) {
                    $order->update([
                        'status'  => 'failed',
                        'message' => 'INSUFFICIENT_MARGIN',
                    ]);
                    broadcast(new AccountUpdated($acct->refresh()));
                    return;
                }

                // Open the order
                $order->update([
                    'open_price'      => $price,
                    'margin_required' => $marginRequired,
                    'status'          => 'open',
                    'open_time'       => now(),
                ]);

                // Margin booking:
                // Recommended: DO NOT decrease balance on open; only increase used_margin.
                $acct->increment('used_margin', $marginRequired);

                // If your accounting model decrements balance at open, uncomment the next line
                // $acct->decrement('balance', $marginRequired);
            } else {
                // Limit/Stop (pending). The controller already put trigger price & expiry on the order,
                // but we ensure here as well in case of retries.
                $order->update([
                    'price'  => $d['trigger_price'] ?? $order->price,
                    'expiry' => $d['expiry'] ?? $order->expiry,
                    'status' => 'pending',
                ]);
            }

            // Broadcast updated account (and order if you have an event for it)
            broadcast(new AccountUpdated($acct->refresh()));
            // event(new OrderProcessed($order->fresh())); // if you use this event
        });
    }
}