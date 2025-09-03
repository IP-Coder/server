<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class TransactionController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $base = [
            'type'   => ['required', Rule::in(['deposit', 'withdrawal'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];

        if ($request->input('type') === 'deposit') {
            $rules = $base + [
                'currency' => ['required', 'string', 'max:50'],
                'chain'    => ['required', 'string', Rule::in(['TRC20', 'BEP20', 'ERC20'])],
                'address'  => ['required', 'string', 'max:255'], // our company wallet
                'receipt'  => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            ];
        } else { // withdrawal (crypto)
            $rules = $base + [
                'currency' => ['required', 'string', 'max:50'],
                'chain'    => ['required', 'string', Rule::in(['TRC20', 'BEP20', 'ERC20'])],
                'address'  => ['required', 'string', 'max:255'], // user's receiving wallet
            ];
        }

        $data = $request->validate($rules);

        $tx = new Transaction();
        $tx->user_id = $user->id;
        $tx->type    = $data['type'];
        $tx->amount  = $data['amount'];
        $tx->status  = 'pending';
        $tx->method  = 'crypto';
        $tx->currency = $data['currency'];
        $tx->chain    = $data['chain'];
        $tx->address  = $data['address'];

        if ($tx->type === 'deposit' && $request->hasFile('receipt')) {
            $tx->receipt_path = $request->file('receipt')->store('receipts', 'public');
        }

        $tx->save();

        return response()->json([
            'success' => true,
            'message' => 'Transaction submitted.',
            'transaction' => $this->present($tx),
        ], 201);
    }

    public function my(Request $request)
    {
        $rows = Transaction::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn($t) => $this->present($t))
            ->values();

        return response()->json($rows);
    }

    private function present(Transaction $t): array
    {
        $receiptUrl = $t->receipt_path ? Storage::url($t->receipt_path) : null;

        return [
            'id'         => $t->id,
            'type'       => $t->type,
            'amount'     => (float) $t->amount,
            'status'     => $t->status,
            'currency'   => $t->currency,
            'chain'      => $t->chain,
            'address'    => $t->address,
            'method'     => $t->method,
            'receipt_url' => $receiptUrl,
            'created_at' => optional($t->created_at)->toIso8601String(),
            'updated_at' => optional($t->updated_at)->toIso8601String(),
        ];
    }
}