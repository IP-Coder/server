<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

use App\Models\Order;
use App\Models\TradingAccount;

class UserController extends Controller
{
    /** GET /me */
    public function me(Request $request)
    {
        $u = $request->user();

        return response()->json([
            'id'            => $u->id,
            'name'          => $u->name,
            'username'      => $u->username,
            'email'         => $u->email,
            'email2'        => $u->email2,
            'mobile'        => $u->mobile,       // primary phone
            'phone_code'    => $u->phone_code,
            'first_name'    => $u->first_name,
            'last_name'     => $u->last_name,
            'birth_day'     => $u->birth_day,
            'birth_month'   => $u->birth_month,
            'birth_year'    => $u->birth_year,
            'address_line'  => $u->address_line,
            'postal_code'   => $u->postal_code,
            'city'          => $u->city,
            'country'       => $u->country,
            'created_at'    => optional($u->created_at)->toIso8601String(),
            'updated_at'    => optional($u->updated_at)->toIso8601String(),
            'account_type'=> $u->account_type,
        ]);
    }

    /** POST /user/update */
    public function update(Request $request)
    {
        $u = $request->user();

        $rules = [
            // account
            'username'      => ['nullable','string','max:100', Rule::unique('users','username')->ignore($u->id)],
            'email'         => ['required','email','max:255', Rule::unique('users','email')->ignore($u->id)],
            'email2'        => ['nullable','email','max:255'],
            'phone_code'    => ['nullable','string','max:10'],
            'mobile'        => ['nullable', 'string', 'max:30'],     // primary phone
            // personal
            'first_name'    => ['nullable','string','max:100'],
            'last_name'     => ['nullable','string','max:100'],
            'birth_day'     => ['nullable','integer','between:1,31'],
            'birth_month'   => ['nullable','integer','between:1,12'],
            'birth_year'    => ['nullable','integer','between:1900,'.date('Y')],
            'address_line'  => ['nullable','string','max:255'],
            'postal_code'   => ['nullable','string','max:30'],
            'city'          => ['nullable','string','max:120'],
            'country'       => ['nullable','string','max:100'],
        ];

        $data = $request->validate($rules);

        // full name ko sync karo (UI me split fields hain)
        if (!empty($data['first_name']) || !empty($data['last_name'])) {
            $full = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
            if ($full !== '') {
                $u->name = $full;
            }
        }

        // email change hone par verify reset (optional)
        if (array_key_exists('email', $data) && $data['email'] !== $u->email) {
            $u->email_verified_at = null;
        }

        // safe mass-assign
        foreach ($data as $k => $v) {
            if ($k === 'email' || $k === 'email2' || $k === 'username') {
                $u->{$k} = $v;
                continue;
            }
            $u->{$k} = $v;
        }

        $u->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated.',
        ]);
    }
    public function switchToLive(Request $request)
    {
        $u = $request->user();

        if ($u->account_type === 'live') {
            return response()->json([
                'success' => false,
                'message' => 'Account is already LIVE.'
            ], 409);
        }

        DB::transaction(function () use ($u) {
            // 1) delete all orders/trades for this user
            Order::where('user_id', $u->id)->delete();

            // 2) reset trading account to 0
            $ta = $u->tradingAccount()->first();
            if ($ta) {
                $ta->balance = 0;
                $ta->equity = 0;
                $ta->used_margin = 0;
                $ta->save();
            } else {
                // if somehow missing, create a fresh zeroed account
                $u->tradingAccount()->create([
                    'account_currency' => 'USD',
                    'balance' => 0,
                    'equity'  => 0,
                    'used_margin' => 0,
                ]);
            }

            // 3) flip account type to live
            $u->account_type = 'live';
            $u->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Switched to LIVE. All demo trades deleted and account reset to 0.',
            'account_type' => 'live',
        ]);
    }
    /** POST /user/change-password */
    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => ['required'],
            'new_password' => [
                'required','string','min:8',
                'regex:/[A-Z]/',  // uppercase
                'regex:/[a-z]/',  // lowercase
                'regex:/\d/',     // digit
            ],
        ]);

        $u = $request->user();

        if (!Hash::check($request->input('old_password'), $u->password)) {
            return response()->json(['message' => 'Old password is incorrect.'], 422);
        }

        $u->password = Hash::make($request->input('new_password'));
        $u->save();

        return response()->json(['success' => true, 'message' => 'Password changed.']);
    }
    public function myReferral(Request $req)
    {
        $u = $req->user();
        $svc = app(\App\Services\ReferralService::class);
        $code = $svc->codeFor($u);

        $q = \App\Models\Affiliate::where('user_id', $u->id);
        return response()->json([
            'code' => $code,
            'referred_count' => (clone $q)->count(),
            'total_reward'   => (float)(clone $q)->sum('reward_amount'),
        ]);
    }
}