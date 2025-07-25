<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\Order;
use App\Models\Instrument;
use App\Events\AccountUpdated;
use App\Services\MarketService;
use App\Jobs\ProcessOrder; // ← our new Job
class OrderController extends Controller
{
    public function __construct(protected MarketService $market) {}

    public function account()
    {
        $acct = Auth::user()->tradingAccount;
        return response()->json(['status' => 'success', 'account' => $acct]);
    }

    // public function placeOrder(Request $request)
    // {
    //     $data = $request->validate([
    //         'symbol'            => 'required|string',
    //         'side'              => 'required|in:buy,sell',
    //         'order_type'        => 'required|in:market,limit,stop',
    //         'volume'            => 'required|numeric|min:0.01',
    //         'leverage'          => 'required|integer|min:1',
    //         'stop_loss_price'   => 'nullable|numeric',
    //         'take_profit_price' => 'nullable|numeric',
    //         'trigger_price'     => 'nullable|numeric',
    //         'expiry'            => 'nullable|date',
    //     ]);

    //     $user = $request->user();
    //     $acct = $user->tradingAccount;

    //     // Pending orders (limit/stop)
    //     if ($data['order_type'] !== 'market') {
    //         $order = $acct->orders()->create([
    //             'user_id'            => $user->id,
    //             'trading_account_id' => $acct->id,
    //             'symbol'             => $data['symbol'],
    //             'type'               => $data['side'],
    //             'volume'             => $data['volume'],
    //             'leverage'           => $data['leverage'],
    //             'open_price'         => null,
    //             'price'              => $data['trigger_price'],
    //             'expiry'             => $data['expiry'],
    //             'stop_loss_price'    => $data['stop_loss_price'],
    //             'take_profit_price'  => $data['take_profit_price'],
    //             'order_type'         => $data['order_type'],
    //             'status'             => 'pending',
    //             'margin_required'    => 0,
    //             'open_time'          => null,
    //             'close_price'        => null,
    //             'close_time'         => null,
    //             'profit_loss'        => null,
    //         ]);
    //         return response()->json(['status' => 'success', 'message' => 'Pending order created', 'data' => $order]);
    //     }

    //     // Market order: fetch live quote
    //     $quote = $this->market->fetchOne($data['symbol']);
    //     $price = $data['side'] === 'buy' ? $quote['data'][0]['ask'] : $quote['data'][0]['bid'];

    //     // Margin calc
    //     $spec            = Instrument::where('symbol', $data['symbol'])->first();
    //     $contractSize    = $spec?->contract_size ?: 100000;
    //     $notional        = $data['volume'] * $contractSize;
    //     $marginRequired  = $notional / $data['leverage'];

    //     if ($acct->balance - $marginRequired < 0) {
    //         throw ValidationException::withMessages(['volume' => ['Insufficient free margin.']]);
    //     }

    //     $order = $acct->orders()->create([
    //         'user_id'            => $user->id,
    //         'trading_account_id' => $acct->id,
    //         'symbol'             => $data['symbol'],
    //         'type'               => $data['side'],
    //         'volume'             => $data['volume'],
    //         'leverage'           => $data['leverage'],
    //         'open_price'         => $price,
    //         'price'              => null,
    //         'expiry'             => null,
    //         'stop_loss_price'    => $data['stop_loss_price'],
    //         'take_profit_price'  => $data['take_profit_price'],
    //         'order_type'         => 'market',
    //         'status'             => 'open',
    //         'margin_required'    => $marginRequired,
    //         'open_time'          => now(),
    //         'close_price'        => null,
    //         'close_time'         => null,
    //         'profit_loss'        => null,
    //     ]);

    //     $acct->decrement('balance', $marginRequired);
    //     $acct->increment('used_margin', $marginRequired);
    //     broadcast(new AccountUpdated($acct));

    //     return response()->json(['status' => 'success', 'order' => $order]);
    // }

    // app/Http/Controllers/OrderController.php



    public function placeOrder(Request $request)
    {
        $data = $request->validate([
            'symbol'            => 'required|string',
            'side'              => 'required|in:buy,sell',
            'order_type'        => 'required|in:market,limit,stop',
            'volume'            => 'required|numeric|min:0.01',
            'leverage'          => 'required|integer|min:1',
            'stop_loss_price'   => 'nullable|numeric',
            'take_profit_price' => 'nullable|numeric',
            'trigger_price'     => 'nullable|numeric',
            'expiry'            => 'nullable|date',
        ]);

        $user = $request->user();
        $acct = $user->tradingAccount;

        // create a placeholder order with minimal data & status “queued”
        $order = $acct->orders()->create([
            'user_id'            => $user->id,
            'trading_account_id' => $acct->id,
            'symbol'             => $data['symbol'],
            'type'               => $data['side'],
            'volume'             => $data['volume'],
            'leverage'           => $data['leverage'],
            'order_type'         => $data['order_type'],
            'stop_loss_price'    => $data['stop_loss_price'],
            'take_profit_price'  => $data['take_profit_price'],
            'price'              => $data['trigger_price'] ?? null,
            'expiry'             => $data['expiry'] ?? null,
            // these will be filled in the Job
            'open_price'         => null,
            'status'             => 'pending',
            'margin_required'    => null,
            'open_time'          => null,
        ]);

        // dispatch a Job to do the real work
        ProcessOrder::dispatch($order->id, $data);

        // return immediately
        return response()->json([
            'status'  => 'success',
            'message' => 'Order received, processing…',
            'data'    => ['order_id' => $order->id, 'status' => 'queued'],
        ]);
    }


    public function orders(Request $request)
    {
        $user   = $request->user();
        $status = $request->query('status');
        $q      = $user->orders();
        $status && $q->where('status', $status);
        $orders = $q->latest()->get();

        $out = [];
        foreach ($orders as $o) {
            $arr = $o->toArray();
            // if ($o->status === 'open') {
            //     $quote = $this->market->fetchOne($o->symbol);
            //     Log:
            //     info($quote['data'][0]['bid']);
            //     $live  = $o->type === 'buy' ? $quote['data'][0]['bid'] : $quote['data'][0]['ask'];
            //     $arr['live_price']           = $live;
            //     $arr['floating_profit_loss'] = $this->calculatePL(
            //         $o->type,
            //         $o->open_price,
            //         $live,
            //         $o->volume
            //     );
            // }
            $out[] = $arr;
        }

        return response()->json(['status' => 'success', 'orders' => $out]);
    }

    public function closeOrder(Request $request)
    {
        $v = $request->validate(['order_id' => 'required|int|exists:orders,id']);
        $user  = $request->user();
        $order = $user->orders()
            ->where('id', $v['order_id'])
            ->where('status', 'open')
            ->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found or already closed.'
            ], 404);
        }

        $quote      = $this->market->fetchOne($order->symbol);
        $closePrice = $order->type === 'buy' ? $quote['data'][0]['bid'] : $quote['data'][0]['ask'];
        $pl         = $this->calculatePL($order->type, $order->open_price, $closePrice, $order->volume);

        $order->update([
            'close_price' => $closePrice,
            'close_time' => now(),
            'profit_loss' => $pl,
            'status' => 'closed',
        ]);

        $acct = $user->tradingAccount;
        $acct->decrement('used_margin', $order->margin_required);
        $acct->increment('balance', $order->margin_required + $pl);
        broadcast(new AccountUpdated($acct));

        return response()->json(['status' => 'success', 'order' => $order]);
    }

    public function updateSlTp(Request $request, Order $order)
    {
        $user = Auth::user();
        if (
            $order->trading_account_id !== $user->tradingAccount->id
            || $order->status !== 'open'
        ) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found or not open.'
            ], 404);
        }

        $d = $request->validate([
            'stop_loss_price'   => 'nullable|numeric|gt:0',
            'take_profit_price' => 'nullable|numeric|gt:0',
        ]);

        $sl = $d['stop_loss_price']   ?? $order->stop_loss_price;
        $tp = $d['take_profit_price'] ?? $order->take_profit_price;

        // sanity: SL/TP vs open price
        if ($order->type === 'buy') {
            if ($sl !== null && $sl >= $order->open_price)
                return response()->json(['status' => 'error', 'message' => 'SL must be below open price'], 422);
            if ($tp !== null && $tp <= $order->open_price)
                return response()->json(['status' => 'error', 'message' => 'TP must be above open price'], 422);
        } else {
            if ($sl !== null && $sl <= $order->open_price)
                return response()->json(['status' => 'error', 'message' => 'SL must be above open price'], 422);
            if ($tp !== null && $tp >= $order->open_price)
                return response()->json(['status' => 'error', 'message' => 'TP must be below open price'], 422);
        }

        $order->update([
            'stop_loss_price'   => $sl,
            'take_profit_price' => $tp,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'SL/TP updated',
            'data' => $order->fresh(),
        ]);
    }

    public function ohlc(Request $request)
    {
        $v = $request->validate([
            'symbol'   => 'required|string',
            'interval' => 'sometimes|int',
            'periods'  => 'sometimes|int|min:1|max:100',
        ]);

        $data = $this->market->fetchOHLC(
            $v['symbol'],
            $v['interval'] ?? 3600,
            $v['periods']  ?? 30
        );

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function symbols()
    {
        $symbols = \App\Models\Symbol::all();
        return response()->json(['status' => 'success', 'symbols' => $symbols]);
    }

    protected function calculatePL($type, $open, $close, $vol)
    {
        return $type === 'buy'
            ? ($close - $open) * $vol
            : ($open  - $close) * $vol;
    }
}