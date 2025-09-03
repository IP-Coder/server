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

        $baseRules = [
            'type'   => ['required', Rule::in(['deposit', 'withdrawal'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];

        if ($request->input('type') === 'deposit') {
            $rules = $baseRules + [
                'currency' => ['required', 'string', 'max:50'],
                'chain'    => ['required', 'string', Rule::in(['TRC20', 'BEP20', 'ERC20'])],
                'address'  => ['required', 'string', 'max:255'],
                'receipt'  => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            ];
        } else {
            $rules = $baseRules + [
                'method'           => ['required', Rule::in(['bank'])],
                'bank_name'        => ['required', 'string', 'max:255'],
                'beneficiary_name' => ['required', 'string', 'max:255'],
                'account_iban'     => ['required', 'string', 'max:255'],
                'country'          => ['required', 'string', 'max:100'],
                'beneficiary_city' => ['nullable', 'string', 'max:255'],
                'bank_address'     => ['nullable', 'string', 'max:255'],
                'routing_number'   => ['nullable', 'string', 'max:100'],
                'swift'            => ['nullable', 'string', 'max:100'],
                'comment'          => ['nullable', 'string'],
            ];
        }

        $data = $request->validate($rules);

        $tx = new Transaction();
        $tx->user_id = $user->id;
        $tx->type    = $data['type'];
        $tx->amount  = $data['amount'];
        $tx->status  = 'pending';

        if ($tx->type === 'deposit') {
            $tx->currency = $data['currency'];
            $tx->chain    = $data['chain'];
            $tx->address  = $data['address'];

            if ($request->hasFile('receipt')) {
                $tx->receipt_path = $request->file('receipt')->store('receipts', 'public');
            }
        } else {
            $tx->method            = $data['method'];
            $tx->bank_name         = $data['bank_name'];
            $tx->beneficiary_city  = $data['beneficiary_city'] ?? null;
            $tx->beneficiary_name  = $data['beneficiary_name'];
            $tx->bank_address      = $data['bank_address'] ?? null;
            $tx->account_iban      = $data['account_iban'];
            $tx->country           = $data['country'];
            $tx->routing_number    = $data['routing_number'] ?? null;
            $tx->swift             = $data['swift'] ?? null;
            $tx->comment           = $data['comment'] ?? null;
        }

        $tx->save();

        return response()->json([
            'success'     => true,
            'message'     => 'Transaction submitted.',
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
        // ✅ FIX: use Storage::url() instead of Storage::disk('public')->url()
        $receiptUrl = $t->receipt_path ? Storage::url($t->receipt_path) : null;

        // Optional hard fallback if needed:
        // if (!$receiptUrl && $t->receipt_path) { $receiptUrl = asset('storage/'.$t->receipt_path); }

        return [
            'id'               => $t->id,
            'type'             => $t->type,
            'amount'           => (float) $t->amount,
            'status'           => $t->status,
            'currency'         => $t->currency,
            'chain'            => $t->chain,
            'address'          => $t->address,
            'method'           => $t->method,
            'bank_name'        => $t->bank_name,
            'beneficiary_city' => $t->beneficiary_city,
            'beneficiary_name' => $t->beneficiary_name,
            'bank_address'     => $t->bank_address,
            'account_iban'     => $t->account_iban,
            'country'          => $t->country,
            'routing_number'   => $t->routing_number,
            'swift'            => $t->swift,
            'comment'          => $t->comment,
            'receipt_url'      => $receiptUrl,
            'created_at'       => optional($t->created_at)->toIso8601String(),
            'updated_at'       => optional($t->updated_at)->toIso8601String(),
        ];
    }
}