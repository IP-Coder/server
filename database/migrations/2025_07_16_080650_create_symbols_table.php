<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('symbols', function (Blueprint $table) {
    $table->id();
    $table->string('symbol')->unique();
    $table->string('title')->nullable();
    $table->integer('decimals')->nullable();
    $table->string('base_symbol')->nullable();
    $table->string('base_title')->nullable();
    $table->string('quote_symbol')->nullable();
    $table->string('quote_title')->nullable();
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('symbols');
    }
};
