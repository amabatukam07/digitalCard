<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->after('email')->index();
            $table->string('role', 40)->default('company_requester')->after('is_admin')->index();
            $table->string('phone', 30)->nullable()->after('role');
            $table->softDeletes();
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('cnpj', 18)->unique();
            $table->string('legal_name', 180);
            $table->string('trade_name', 180);
            $table->string('state_registration', 40)->nullable();
            $table->string('email', 180);
            $table->string('phone', 30)->nullable();
            $table->string('billing_address', 255)->nullable();
            $table->string('billing_cep', 10)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->boolean('require_order_approval')->default(true);
            $table->decimal('low_balance_threshold', 12, 2)->default(200.00);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('company_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('company_role', 30)->default('requester')->index();
            $table->decimal('spending_limit', 12, 2)->nullable();
            $table->string('department', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
        });

        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('delivery');
            $table->string('label', 80)->default('Principal');
            $table->string('recipient_name', 120);
            $table->string('cep', 10);
            $table->string('street', 180);
            $table->string('number', 30);
            $table->string('complement', 120)->nullable();
            $table->string('neighborhood', 120);
            $table->string('city', 120);
            $table->string('state', 2);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('company_users');
        Schema::dropIfExists('companies');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropIndex(['is_admin']);
            $table->dropIndex(['role']);
            $table->dropColumn(['is_admin', 'role', 'phone']);
        });
    }
};
