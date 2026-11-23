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
        Schema::table('virtual_cards', function (Blueprint $table): void {
            $table->dropUnique('virtual_cards_card_number_unique');
        });

        Schema::table('virtual_cards', function (Blueprint $table): void {
            $table->text('card_number')->change();
            $table->text('cvv')->change();
            $table->decimal('daily_limit', 12, 2)->nullable()->after('cashback_earned');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_idempotency_key_unique');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unique(['user_id', 'idempotency_key'], 'orders_user_idempotency_unique');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('product_name', 255)->nullable()->after('product_id');
            $table->string('product_sku', 60)->nullable()->after('product_name');
            $table->decimal('cashback_percent', 5, 2)->nullable()->after('unit_price');
            $table->decimal('discount_allocated', 12, 2)->default(0.00)->after('cashback_percent');
        });

        Schema::table('card_transactions', function (Blueprint $table): void {
            $table->string('reference_type', 150)->nullable()->after('description');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            $table->string('idempotency_key', 120)->nullable()->unique()->after('reference_id');
            $table->index(['reference_type', 'reference_id'], 'idx_card_tx_reference');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 30)->default('customer')->after('is_admin')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });

        Schema::table('card_transactions', function (Blueprint $table): void {
            $table->dropIndex('idx_card_tx_reference');
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['reference_type', 'reference_id', 'idempotency_key']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['product_name', 'product_sku', 'cashback_percent', 'discount_allocated']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_user_idempotency_unique');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unique('idempotency_key', 'orders_idempotency_key_unique');
        });

        Schema::table('virtual_cards', function (Blueprint $table): void {
            $table->dropColumn('daily_limit');
            $table->string('card_number', 19)->change();
            $table->string('cvv', 3)->change();
        });

        Schema::table('virtual_cards', function (Blueprint $table): void {
            $table->unique('card_number', 'virtual_cards_card_number_unique');
        });
    }
};
