<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->decimal('executed_price', 16, 8);
            $table->decimal('executed_size', 16, 8);
            $table->timestamp('executed_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('trades');
    }
};