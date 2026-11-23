<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 24)->unique();
            $table->foreignId('purchaser_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('redeemer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('initial_amount', 12, 2);
            $table->decimal('remaining_amount', 12, 2);
            $table->string('recipient_name', 120)->nullable();
            $table->string('recipient_email', 180)->nullable();
            $table->string('message', 255)->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('price_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('target_price', 12, 2);
            $table->timestamp('notified_at')->nullable();
            $table->decimal('last_notified_price', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
            $table->index(['product_id', 'notified_at', 'target_price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_alerts');
        Schema::dropIfExists('gift_cards');
    }
};
