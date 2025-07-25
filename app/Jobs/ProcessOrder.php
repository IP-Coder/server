<?php

// app/Jobs/ProcessOrder.php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Instrument;
use App\Events\OrderProcessed;
use App\Events\AccountUpdated;
use App\Services\MarketService;
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

    public function handle(MarketService $market)
    {
        // Wrap in a transaction to keep balances & order in sync
        DB::transaction(function () use ($market) {
            $order = Order::lockForUpdate()->find($this->orderId);
            $acct  = $order->tradingAccount;
            $d     = $this->data;

            // 1) Market order vs pending
            if ($d['order_type'] === 'market') {
                // fetch live quote
                $quote = $market->fetchOne($d['symbol']);
                $price = $d['side'] === 'buy'
                    ? $quote['data'][0]['ask']
                    : $quote['data'][0]['bid'];

                // margin calc
                $spec           = Instrument::where('symbol', $d['symbol'])->first();
                $contractSize   = $spec?->contract_size ?: 100_000;
                $notional       = $d['volume'] * $contractSize;
                $marginRequired = $notional / $d['leverage'];

                if ($acct->balance < $marginRequired) {
                    // mark failed
                    $order->update([
                        'status'  => 'failed',
                        'message' => 'Insufficient margin',
                    ]);
                    return;
                }

                // update the order record
                $order->update([
                    'open_price'      => $price,
                    'margin_required' => $marginRequired,
                    'status'          => 'open',
                    'open_time'       => now(),
                ]);

                // adjust account
                $acct->decrement('balance', $marginRequired);
                $acct->increment('used_margin', $marginRequired);
            } else {
                // limit/stop: stay pending until external trigger or cron
                $order->update(['status' => 'pending']);
            }

            // Broadcast that the order has been processed (open or pending or failed)
            // broadcast(new OrderProcessed($order->fresh()));

            // Also broadcast updated account balances
            broadcast(new AccountUpdated($acct->refresh()));
        });
    }
}
