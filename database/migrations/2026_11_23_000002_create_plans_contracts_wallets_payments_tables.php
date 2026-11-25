<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 80);
            $table->decimal('price', 12, 2);
            $table->decimal('credits_amount', 12, 2);
            $table->boolean('is_custom_amount')->default(false);
            $table->decimal('min_amount', 12, 2)->nullable();
            $table->string('target_profile', 150);
            $table->text('description')->nullable();
            $table->json('benefits');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->decimal('contracted_amount', 12, 2);
            $table->decimal('credits_granted', 12, 2);
            $table->string('status', 30)->default('pending_payment')->index();
            $table->string('terms_version', 30)->default('Modelo-A-v1');
            $table->timestamp('accepted_terms_at')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('credit_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 12, 2)->default(0.00);
            $table->decimal('reserved_balance', 12, 2)->default(0.00);
            $table->decimal('total_acquired', 12, 2)->default(0.00);
            $table->decimal('total_spent', 12, 2)->default(0.00);
            $table->decimal('daily_limit', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('low_balance_notified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_method', 30)->default('pix_dynamic');
            $table->string('txid', 64)->nullable()->unique();
            $table->text('pix_payload')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->string('receipt_path', 255)->nullable();
            $table->string('receipt_hash', 64)->nullable()->unique();
            $table->string('status', 30)->default('pendente')->index();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('credit_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credit_wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40)->index();
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('description', 255);
            $table->string('reason', 255)->nullable();
            $table->string('reference_type', 150)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reference_type', 'reference_id'], 'idx_credit_tx_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('credit_wallets');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('plans');
    }
};
