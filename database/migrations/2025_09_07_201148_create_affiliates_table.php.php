<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();          // referrer
            $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete(); // new signup
            $table->decimal('reward_amount', 20, 2)->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'referred_user_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('affiliates');
    }
};
