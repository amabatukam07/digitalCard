<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->decimal('subtotal_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->string('coupon_code', 50)->nullable();
            $table->decimal('shipping_cost', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('credits_debited', 12, 2)->default(0.00);
            $table->decimal('total_cost_snapshot', 12, 2)->default(0.00);
            $table->decimal('estimated_das_amount', 12, 2)->default(0.00);
            $table->decimal('estimated_margin_amount', 12, 2)->default(0.00);
            $table->string('status', 50)->default('Produto solicitado')->index();
            $table->string('shipping_address', 255);
            $table->string('shipping_cep', 15)->nullable();
            $table->string('tracking_code', 100)->nullable();
            $table->string('department', 80)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'idempotency_key'], 'orders_user_idempotency_unique');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name', 255)->nullable();
            $table->string('product_sku', 60)->nullable();
            $table->string('unit_presentation', 120)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost_snapshot', 12, 2)->default(0.00);
            $table->decimal('das_rate_snapshot', 5, 2)->default(4.00);
            $table->decimal('discount_allocated', 12, 2)->default(0.00);
            $table->string('item_status', 50)->default('Produto solicitado')->index();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 40)->default('scheduled')->index();
            $table->string('carrier', 100)->nullable();
            $table->string('tracking_code', 100)->nullable();
            $table->string('scheduled_route', 120)->nullable();
            $table->string('receiver_name', 120)->nullable();
            $table->string('proof_of_delivery', 255)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 120)->index();
            $table->string('auditable_type', 150);
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_auditable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
