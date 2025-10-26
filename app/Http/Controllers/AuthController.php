<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\TradingAccount;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\Affiliate;

class AuthController extends Controller
{
    /**
     * Register a new user and issue a token.
     */
    public function signup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'email'                => 'required|email|unique:users,email',
            'password'             => 'required|string|min:8|confirmed',
            'account_type'         => ['required', Rule::in(['demo', 'live'])],
            'referral_code'        => 'nullable|string|max:50', // optional
            'agent_code' => 'nullable|string|max:50', // optional
        ]);

        $DEMO_CREDIT = 100000.00; // 1 lakh

        $result = DB::transaction(function () use ($data, $DEMO_CREDIT) {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
                'account_type'      => $data['account_type'],
                'agent_code'      => $data['agent_code'] ?? null,
        ]);
            if (!empty($data['referral_code'])) {
                $rewardAmount = 0.0; // referral reward amount
                $referrer = User::where('referral_code', $data['referral_code'])->first();
                if ($referrer && $referrer->id !== $user->id) {
                    // a) link who referred (if your users table has referred_by)
                    $user->referred_by = $referrer->id;
                    $user->save();
                    // b) ledger entry (affiliates table)
                    Affiliate::firstOrCreate(
                        ['user_id' => $referrer->id, 'referred_user_id' => $user->id],
                        ['reward_amount' => $rewardAmount]
                    );
                }
            }

            $isDemo = $data['account_type'] === 'demo';
            $initial = $isDemo ? $DEMO_CREDIT : 0.00;

            $account = TradingAccount::create([
                'user_id'          => $user->id,
                'account_currency' => 'USD', // change if you want INR
                'balance'          => $initial,
                'equity'           => $initial,
                'used_margin'      => 0.00,
            ]);

            if ($isDemo) {
                Transaction::create([
                    'user_id'  => $user->id,
                    'type'     => 'deposit',
                    'amount'   => $DEMO_CREDIT,
                    'currency' => 'USD',
                    'method'   => 'demo-credit',
                    'comment'  => 'Signup demo bonus',
                    'status'   => 'approved',
                ]);
            }

            $token = $user->createToken('api_token')->plainTextToken;

            return compact('user', 'account', 'token');
        });

        return response()->json($result, 201);
    }

    /**
     * Authenticate and issue a token.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke existing tokens
        $user->tokens()->delete();

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    /**
     * Revoke the token (logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out'], 200);
    }
}