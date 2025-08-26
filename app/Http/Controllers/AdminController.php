<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Models\TradingAccount;
use Illuminate\Http\JsonResponse;

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

    public function closeTrade($orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        $order->status = 'closed';
        $order->save();

        return response()->json(['message' => 'Trade closed', 'order' => $order]);
    }
}
