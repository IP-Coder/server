<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'phone2')) {
                $table->dropColumn('phone2');
            }
            if (Schema::hasColumn('users', 'phone2_code')) {
                $table->dropColumn('phone2_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone2_code', 10)->nullable();
            $table->string('phone2', 30)->nullable();
        });
    }
};
