<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // rename old columns
            $table->renameColumn('market_symbol', 'symbol');
            $table->renameColumn('size',          'volume');

            // new foreign key to trading_accounts
            $table->foreignId('trading_account_id')
                ->after('user_id')
                ->constrained()
                ->onDelete('cascade');

            // order details
            $table->integer('leverage')->after('volume');
            $table->enum('order_type', ['market', 'limit', 'stop'])
                ->default('market')
                ->after('type');
            $table->decimal('price', 16, 8)           // trigger price for pending; keep name
                ->change();
            $table->decimal('open_price', 16, 8)
                ->nullable()
                ->after('price');
            $table->dateTime('expiry')
                ->nullable()
                ->after('open_price');
            $table->decimal('stop_loss_price', 16, 8)
                ->nullable()
                ->after('expiry');
            $table->decimal('take_profit_price', 16, 8)
                ->nullable()
                ->after('stop_loss_price');
            $table->decimal('margin_required', 16, 8)
                ->default(0)
                ->after('order_type');
            $table->dateTime('open_time')
                ->nullable()
                ->after('margin_required');
            $table->decimal('close_price', 16, 8)
                ->nullable()
                ->after('open_time');
            $table->dateTime('close_time')
                ->nullable()
                ->after('close_price');
            $table->decimal('profit_loss', 16, 8)
                ->nullable()
                ->after('close_time');

            // extend status enum
            $table->enum('status', ['open', 'pending', 'closed', 'cancelled'])
                ->default('open')
                ->change();
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            // reverse the above (e.g. drop new columns, rename back)…
            $table->renameColumn('symbol',        'market_symbol');
            $table->renameColumn('volume',        'size');
            $table->dropForeign(['trading_account_id']);
            $table->dropColumn([
                'trading_account_id',
                'leverage',
                'order_type',
                'open_price',
                'expiry',
                'stop_loss_price',
                'take_profit_price',
                'margin_required',
                'open_time',
                'close_price',
                'close_time',
                'profit_loss',
            ]);
            $table->enum('status', ['open', 'filled', 'cancelled'])
                ->default('open')
                ->change();
            // note: resetting price column type if you wish
        });
    }
};