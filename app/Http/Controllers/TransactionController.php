<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\TradingAccount;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    // Create deposit/withdrawal request
    public function create(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:deposit,withdrawal',
            'amount' => 'required|numeric|min:1',
        ]);

        $transaction = Transaction::create([
            'user_id' => Auth::id(),
            'type' => $data['type'],
            'amount' => $data['amount'],
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Transaction request submitted!', 'transaction' => $transaction]);
    }

    // View user’s transactions
    public function myTransactions()
    {
        $transactions = Transaction::where('user_id', Auth::id())->latest()->get();
        return response()->json($transactions);
    }
    public function userTransactions($id)
    {
        $transactions = Transaction::where('user_id', $id)->latest()->get();
        return response()->json([
            'transactions' => $transactions
        ]);
    }
    // (For Admin) Approve / Reject
    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $transaction = Transaction::findOrFail($id);

        // Only update if not already approved/rejected
        if ($transaction->status !== 'pending') {
            return response()->json(['message' => 'Transaction already processed'], 400);
        }

        DB::transaction(function () use ($transaction, $data) {
            $transaction->status = $data['status'];
            $transaction->save();

            // Only update balance if approved
            if ($data['status'] === 'approved') {
                $account = TradingAccount::where('user_id', $transaction->user_id)->first();

                if (!$account) {
                    throw new \Exception("Trading account not found for user");
                }

                if ($transaction->type === 'deposit') {
                    $account->balance += $transaction->amount;
                    $account->equity += $transaction->amount; // equity bhi update
                } elseif ($transaction->type === 'withdrawal') {
                    if ($account->balance < $transaction->amount) {
                        throw new \Exception("Insufficient balance for withdrawal");
                    }
                    $account->balance -= $transaction->amount;
                    $account->equity -= $transaction->amount;
                }

                $account->save();
            }
        });

        return response()->json([
            'message' => 'Transaction status updated',
            'transaction' => $transaction,
        ]);
    }
}
