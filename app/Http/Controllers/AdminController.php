<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Models\TradingAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Events\AccountUpdated;
use App\Models\Transaction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Models\KycSubmission; // ⬅️ NEW
use App\Models\SupportTicket; // ⬅️ NEW
use Illuminate\Support\Facades\Log;


class AdminController extends Controller
{
    public function listUsers(Request $request): JsonResponse
    {
        $query = User::with('tradingAccount:id,user_id,account_currency,balance,equity,used_margin')
            ->select('id', 'name', 'email', 'mobile', 'account_type'); // ⬅️ add these

        // Optional: only_live=1 dene par sirf LIVE users bhejo
        if ($request->boolean('only_live')) {
            $query->where('account_type', 'live');
        }

        $users = $query->get();

        return response()->json(['users' => $users]);
    }

    public function userAccount($id): JsonResponse
    {
        $account = TradingAccount::with('user:id,name,email,mobile,status')
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
    /**
     * GET /api/users/{id}/transactions  (admin only)
     * Return the user’s transactions for the admin UI.
     */
    public function userTransactions(int $id): \Illuminate\Http\JsonResponse
    {
        // Ensure the user exists (and eager-load if you prefer)
        $user = \App\Models\User::findOrFail($id);

        $txs = Transaction::where('user_id', $id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Transaction $t) {
                return [
                    'id'          => $t->id,
                    'type'        => $t->type,           // 'deposit' | 'withdrawal'
                    'amount'      => (float)$t->amount,
                    'status'      => $t->status,         // 'pending' | 'approved' | 'rejected'
                    'currency'    => $t->currency,
                    'chain'       => $t->chain,
                    'address'     => $t->address,
                    'method'      => $t->method,
                    'receipt_url' => $receiptUrl = $t->receipt_path ? asset('storage/' . ltrim($t->receipt_path, '/')) : null,
                    'created_at'  => optional($t->created_at)->toIso8601String(),
                    'updated_at'  => optional($t->updated_at)->toIso8601String(),
                ];
            });

        return response()->json(['transactions' => $txs]);
    }

    /**
     * POST /api/transactions/{id}/status  (admin only)
     * Body: { "status": "approved" | "rejected" }
     *
     * - Approving a DEPOSIT increases account balance.
     * - Approving a WITHDRAWAL decreases account balance (checks sufficient balance).
     * - Rejecting does not touch balance.
     * - Idempotent: if already processed with same status, no double-apply.
     */
    public function updateTransactionStatus(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        /** @var Transaction $tx */
        $tx = Transaction::with('user.tradingAccount')->findOrFail($id);

        // Only pending can be processed
        if ($tx->status !== 'pending') {
            if ($tx->status === $data['status']) {
                return response()->json([
                    'status'  => 'ok',
                    'message' => 'Transaction already processed.',
                ]);
            }
            return response()->json([
                'status'  => 'error',
                'message' => 'Only pending transactions can be updated.',
            ], 422);
        }

        // Must have a trading account to post balance effects
        $acct = optional($tx->user)->tradingAccount;
        if (!$acct) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Trading account not found for this user.',
            ], 422);
        }

        DB::transaction(function () use ($data, $tx, $acct) {
            $new = $data['status'];

            if ($new === 'approved') {
                if ($tx->type === 'deposit') {
                    // Credit balance
                    $twoXamount = $tx->amount * 2;
                    $acct->increment('balance', $twoXamount);
                    $acct->increment('equity',  $twoXamount);
                } elseif ($tx->type === 'withdrawal') {
                    // Ensure sufficient funds (simple guard)
                    if ($acct->balance < $tx->amount) {
                        // Throwing will rollback
                        throw new \RuntimeException('Insufficient balance to approve withdrawal.');
                    }
                    // Debit balance
                    $acct->decrement('balance', $tx->amount);
                    $acct->decrement('equity',  $tx->amount);
                }
            }

            // Persist transaction status
            $tx->status = $new;
            $tx->save();

            // Optional: broadcast account update if you already use this elsewhere
            if (class_exists(\App\Events\AccountUpdated::class)) {
                broadcast(new \App\Events\AccountUpdated($acct));
            }
        });

        // Reload fresh state
        $tx->refresh();
        $acct->refresh();

        return response()->json([
            'status'       => 'success',
            'transaction'  => [
                'id'     => $tx->id,
                'status' => $tx->status,
            ],
            'account'      => [
                'balance' => (float)$acct->balance,
                'equity'  => (float)$acct->equity,
            ],
        ]);
    }
    public function userKyc(int $id): JsonResponse
    {
        $user = \App\Models\User::findOrFail($id);

        $subs = KycSubmission::where('user_id', $id)
            ->orderByDesc('id')
            ->get()
            ->map(function ($s) {
                return [
                    'id'               => $s->id,
                    'status'           => $s->status,
                    'id_number_last4'  => $s->id_number_last4,
                    'document_url'     => $s->document_path ? asset('storage/' . ltrim($s->document_path, '/')) : null,
                    'selfie_url'       => $s->selfie_path ? asset('storage/' . ltrim($s->selfie_path, '/')) : null,
                    'review_notes'     => $s->review_notes,
                    'created_at'       => optional($s->created_at)->toIso8601String(),
                    'updated_at'       => optional($s->updated_at)->toIso8601String(),
                ];
            });

        return response()->json(['kyc' => $subs]);
    }

    /**
     * POST /api/kyc/{id}/status (admin)
     * Body: { "status": "approved" | "rejected", "notes": "..." }
     */
    public function updateKycStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'notes'  => ['nullable', 'string', 'max:2000'],
        ]);

        $kyc = KycSubmission::findOrFail($id);
        $kyc->status = $data['status'];
        $kyc->review_notes = $data['notes'] ?? null;
        $kyc->save();

        return response()->json([
            'status' => 'success',
            'kyc'    => [
                'id'           => $kyc->id,
                'status'       => $kyc->status,
                'review_notes' => $kyc->review_notes,
            ],
        ]);
    }
    public function referralStats(Request $request, string $agentId): JsonResponse
    {
        Log::info("Fetching referral stats for agent: " . $agentId);
        $query = User::with('tradingAccount:id,user_id,account_currency,balance,equity,used_margin')
            ->select('id', 'name', 'email', 'mobile', 'account_type')
            ->where('agent_code', $agentId); // ⬅️ add these

        // Optional: only_live=1 dene par sirf LIVE users bhejo
        if ($request->boolean('only_live')) {
            $query->where('account_type', 'live');
        }

        $users = $query->get();

        return response()->json(['users' => $users]);
    }

    public function supportTickets(Request $request): JsonResponse
    {
        $status = $request->query('status'); // optional: open|pending|closed|resolved
        $q      = $request->query('q');      // optional search (ticket_no, subject, message, email)

        $tickets = SupportTicket::with('user:id,name,email')
            ->when($status, fn($qq) => $qq->where('status', $status))
            ->when($q, function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('ticket_no', 'like', "%{$q}%")
                        ->orWhere('subject', 'like', "%{$q}%")
                        ->orWhere('message', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit(500) // simple cap; swap to paginate() if you prefer
            ->get()
            ->map(function (SupportTicket $t) {
                return [
                    'id'         => $t->id,
                    'ticket_no'  => $t->ticket_no,
                    'subject'    => $t->subject,
                    'status'     => $t->status,
                    'name'       => $t->name,
                    'email'      => $t->email,
                    'phone'      => trim(($t->phone_code ? "+{$t->phone_code} " : '') . $t->phone),
                    'message'    => $t->message,
                    'attachments' => $t->attachments,
                    'source'     => $t->source,
                    'created_at' => optional($t->created_at)->toIso8601String(),
                    'user'       => $t->user ? [
                        'id'    => $t->user->id,
                        'name'  => $t->user->name,
                        'email' => $t->user->email,
                    ] : null,
                ];
            });

        return response()->json(['tickets' => $tickets]);
    }

    public function supportTicketShow(int $id): JsonResponse
    {
        $t = SupportTicket::with('user:id,name,email')->findOrFail($id);

        return response()->json([
            'ticket' => [
                'id'         => $t->id,
                'ticket_no'  => $t->ticket_no,
                'subject'    => $t->subject,
                'status'     => $t->status,
                'name'       => $t->name,
                'email'      => $t->email,
                'phone'      => trim(($t->phone_code ? "+{$t->phone_code} " : '') . $t->phone),
                'message'    => $t->message,
                'attachments' => $t->attachments,
                'source'     => $t->source,
                'created_at' => optional($t->created_at)->toIso8601String(),
                'user'       => $t->user ? [
                    'id'    => $t->user->id,
                    'name'  => $t->user->name,
                    'email' => $t->user->email,
                ] : null,
            ],
        ]);
    }

    public function updateSupportTicketStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'pending', 'closed', 'resolved'])],
        ]);

        $t = SupportTicket::findOrFail($id);
        $t->status = $data['status'];
        $t->save();

        return response()->json(['status' => 'success', 'ticket' => ['id' => $t->id, 'status' => $t->status]]);
    }
    // function to upate the user account status (active/disabled)
    public function updateUserStatus(Request $request, $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'disabled'])],
        ]);

        $user = User::findOrFail($id);
        $user->status = $data['status'];
        $user->save();

        return response()->json(['status' => 'success', 'user' => ['id' => $user->id, 'status' => $user->status]]);
    }
}