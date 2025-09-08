<?php

namespace App\Services;

use App\Models\User;
use App\Models\Affiliate;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    // Deterministic code: "R" + base36(user_id)
    public function codeFor(User $user): string
    {
        return 'R' . strtoupper(base_convert((string)$user->id, 10, 36));
    }

    public function referrerFromCode(?string $code): ?User
    {
        if (!$code) return null;
        $code = strtoupper(trim($code));
        if (!str_starts_with($code, 'R')) return null;
        $base36 = substr($code, 1);
        if ($base36 === '') return null;
        $id = intval(base_convert($base36, 36, 10));
        if ($id <= 0) return null;
        return User::find($id);
    }

    public function handleReferral(string $code, User $newUser, float $reward = 100.0): bool
    {
        $ref = $this->referrerFromCode($code);
        if (!$ref) return false;
        if ($ref->id === $newUser->id) return false; // self-referral guard

        return DB::transaction(function () use ($ref, $newUser, $reward) {
            // idempotency: skip if already recorded
            $exists = Affiliate::where('user_id', $ref->id)
                ->where('referred_user_id', $newUser->id)
                ->exists();
            if ($exists) return true;

            Affiliate::create([
                'user_id' => $ref->id,
                'referred_user_id' => $newUser->id,
                'reward_amount' => $reward,
            ]);

            // Credit first trading account (table already exists):contentReference[oaicite:5]{index=5}
            $acc = $ref->tradingAccounts()->first();
            if (!$acc) {
                $acc = $ref->tradingAccounts()->create([
                    'account_currency' => 'USD',
                ]);
            }
            $acc->balance = (float)$acc->balance + $reward;
            $acc->equity  = (float)$acc->equity  + $reward;
            $acc->save();

            return true;
        });
    }
}
