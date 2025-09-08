<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 32)->nullable()->unique()->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'referred_by')) {
                $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete()->after('referral_code');
            }
        });

        // Backfill referral_code for existing users
        $existing = DB::table('users')->select('id', 'referral_code')->get();
        foreach ($existing as $row) {
            if (!$row->referral_code) {
                // Ensure uniqueness by simple retry loop
                $tries = 0;
                do {
                    $code = strtoupper(Str::random(8));
                    $exists = DB::table('users')->where('referral_code', $code)->exists();
                    $tries++;
                } while ($exists && $tries < 5);

                DB::table('users')->where('id', $row->id)->update(['referral_code' => $code]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'referred_by')) {
                $table->dropConstrainedForeignId('referred_by');
            }
            if (Schema::hasColumn('users', 'referral_code')) {
                $table->dropColumn('referral_code');
            }
        });
    }
};
