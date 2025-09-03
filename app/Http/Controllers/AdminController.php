<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Models\TradingAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Events\AccountUpdated;

class AdminController extends Controller
{
    public function listUsers(): JsonResponse
    {
        $users = User::with('tradingAccount:id,user_id,balance,equity,used_margin')
            ->select('id', 'name', 'email')
            ->get();

        return response()->json(['users' => $users]);
    }

    public function userAccount($id): JsonResponse
    {
        $account = TradingAccount::with('user:id,name,email,mobile')
            ->where('user_id', $id)
            ->first();

        if (! $account) {
            return response()->json(['status' => 'error', 'message' => 'No account found'], 404);
        }

        return response()->json([
            'status'  => 'success',
            'account' => $account,
        ]);
    }

    public function userTrades($id): JsonResponse
    {
        $open = Order::where('user_id', $id)->where('status', 'open')->get();
        $closed = Order::where('user_id', $id)->where('status', 'closed')->get();

        return response()->json([
            'open_trades' => $open,
            'closed_trades' => $closed,
        ]);
    }

    // public function closeTrade($orderId): JsonResponse
    // {
    //     $order = Order::findOrFail($orderId);
    //     $order->status = 'closed';
    //     $order->save();

    //     return response()->json(['message' => 'Trade closed', 'order' => $order]);
    // }
    public function closeTrade(Request $request, $orderId): JsonResponse
    {
        $v = $request->validate([
            'close_price' => 'required|numeric',
            'profit_loss' => 'required|numeric',
            'volume' => 'nullable|numeric|min:0.01',
        ]);

        $order = Order::where('id', $orderId)
            ->where('status', 'open')
            ->first();

        if (! $order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found or already closed.'
            ], 404);
        }

        $closePrice  = $v['close_price'];
        $pl          = $v['profit_loss'];
        $closeVolume = $v['volume'] ?? $order->volume;

        // Update order
        $order->update([
            'close_price' => $closePrice,
            'close_time'  => now(),
            'profit_loss' => $pl,
            'volume'      => $closeVolume,
            'status'      => 'closed',
        ]);

        // Update account
        $acct = $order->user->tradingAccount;
        $acct->decrement('used_margin', $order->margin_required);
        $acct->increment('balance', $order->margin_required + $pl);

        broadcast(new AccountUpdated($acct));

        return response()->json([
            'status' => 'success',
            'order'  => $order,
        ]);
    }
}