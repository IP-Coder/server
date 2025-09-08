<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;

class ReferralController extends Controller
{
    /**
     * GET /api/refer/my
     * Returns:
     *  {
     *    code: string,
     *    referred_count: number,
     *    total_reward: number
     *  }
     */
    public function my(Request $request)
    {
        $user = $request->user();

        // Ensure user has a referral_code (if you've already done this elsewhere, this is a safe fallback)
        if (empty($user->referral_code)) {
            $user->referral_code = $this->generateUniqueReferralCode();
            $user->save();
        }

        // Count and Sum from affiliates
        $referredCount = DB::table('affiliates')
            ->where('user_id', $user->id)
            ->count();

        $totalReward = (float) DB::table('affiliates')
            ->where('user_id', $user->id)
            ->sum('reward_amount');

        return response()->json([
            'code'            => $user->referral_code,  // frontend will build link
            'referred_count'  => $referredCount,
            'total_reward'    => $totalReward,
        ]);
    }

    /**
     * GET /api/refer/history
     * Returns: Array of rows
     *  [
     *    { id, referred_name, referred_email, reward_amount, created_at },
     *    ...
     *  ]
     */
    public function history(Request $request)
    {
        $uid = $request->user()->id;

        // Join with users table to show referred user's name/email
        $rows = DB::table('affiliates')
            ->leftJoin('users as ru', 'ru.id', '=', 'affiliates.referred_user_id')
            ->where('affiliates.user_id', $uid)
            ->orderByDesc('affiliates.created_at')
            ->limit(500) // sane upper bound
            ->get([
                'affiliates.id',
                'affiliates.reward_amount',
                'affiliates.created_at',
                DB::raw('ru.name as referred_name'),
                DB::raw('ru.email as referred_email'),
            ]);

        // 그대로 return; FE expects these exact keys
        return response()->json($rows);
    }

    /**
     * Helper: make a unique referral code (8 chars, uppercase).
     * If you already generate codes during registration, this is just a fallback.
     */
    private function generateUniqueReferralCode(int $len = 8): string
    {
        do {
            $code = strtoupper(Str::random($len));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}
