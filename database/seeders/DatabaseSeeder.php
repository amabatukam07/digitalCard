<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Contract;
use App\Models\Coupon;
use App\Models\CreditTransaction;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ProductReview;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\CartPricing;
use App\Services\WalletLedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            CatalogSeeder::class,
        ]);

        if (! app()->environment(['local', 'testing'])) {
            $this->command?->info('Ambiente de produção detectado: contas de demonstração ignoradas.');

            return;
        }

        $adminPlainPassword = (string) env('ADMIN_INITIAL_PASSWORD', '');
        $generatedAdminPassword = false;
        if ($adminPlainPassword === '') {
            $adminPlainPassword = Str::password(16);
            $generatedAdminPassword = true;
        }

        $clientPlainPassword = (string) env('DEMO_CLIENT_PASSWORD', '');
        $generatedClientPassword = false;
        if ($clientPlainPassword === '') {
            $clientPlainPassword = Str::password(16);
            $generatedClientPassword = true;
        }

        // 1. Equipe Backoffice NexoSupri
        $admin = $this->upsertBackofficeUser('admin@nexosupri.com.br', 'Administrador NexoSupri', $adminPlainPassword, UserRole::Admin, true);
        $this->upsertBackofficeUser('financeiro@nexosupri.com.br', 'Tesouraria NexoSupri', $adminPlainPassword, UserRole::Finance, false);
        $comprasUser = $this->upsertBackofficeUser('compras@nexosupri.com.br', 'Compras & Estoque NexoSupri', $adminPlainPassword, UserRole::Purchasing, false);
        $this->upsertBackofficeUser('logistica@nexosupri.com.br', 'Operação & Logística NexoSupri', $adminPlainPassword, UserRole::Logistics, false);

        // 2. Fornecedores e Pedido de Compra inicial
        $supplierPapel = Supplier::updateOrCreate(
            ['cnpj' => '22.333.444/0001-55'],
            [
                'legal_name' => 'Distribuidora Paulista de Papéis e Escritório Ltda',
                'trade_name' => 'Papel & Suprimentos SP',
                'contact_name' => 'Ricardo Almeida',
                'email' => 'comercial@papeisp.com.br',
                'phone' => '(11) 3456-7890',
                'payment_terms' => '28 dias boleto',
                'lead_time_days' => 2,
                'is_active' => true,
            ]
        );

        $supplierLimpeza = Supplier::updateOrCreate(
            ['cnpj' => '33.444.555/0001-66'],
            [
                'legal_name' => 'Atacado Higieniza Brasil S.A.',
                'trade_name' => 'Higieniza Atacado',
                'contact_name' => 'Camila Ferreira',
                'email' => 'vendas@higieniza.com.br',
                'phone' => '(11) 3987-6543',
                'payment_terms' => '14/28 dias',
                'lead_time_days' => 3,
                'is_active' => true,
            ]
        );

        $supplierCopa = Supplier::updateOrCreate(
            ['cnpj' => '44.555.666/0001-77'],
            [
                'legal_name' => 'Norte Sul Bebidas e Copa Distribuidora Ltda',
                'trade_name' => 'Copa & Bebidas NS',
                'contact_name' => 'Eduardo Santos',
                'email' => 'pedidos@copabebidas.com.br',
                'phone' => '(11) 3123-4567',
                'payment_terms' => 'PIX / 15 dias',
                'lead_time_days' => 2,
                'is_active' => true,
            ]
        );

        if ($papelA4 = Product::where('slug', 'papel-a4-500-folhas')->first()) {
            SupplierProduct::updateOrCreate(
                ['supplier_id' => $supplierPapel->id, 'product_id' => $papelA4->id],
                ['supplier_sku' => 'CHX-A4-500', 'supplier_cost' => 24.00, 'lead_time_days' => 2, 'last_quoted_at' => now()]
            );

            if (! PurchaseOrder::where('invoice_number', 'NF-000142')->exists()) {
                $po = PurchaseOrder::create([
                    'supplier_id' => $supplierPapel->id,
                    'created_by' => $comprasUser->id,
                    'status' => PurchaseOrder::STATUS_RECEIVED,
                    'invoice_number' => 'NF-000142',
                    'total_amount' => 1250.00,
                    'freight_amount' => 50.00,
                    'ordered_at' => now()->subDays(7),
                    'received_at' => now()->subDays(5),
                    'notes' => 'Abastecimento inicial de estoque para contratos Business e Corporate.',
                ]);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $papelA4->id,
                    'quantity' => 50,
                    'unit_cost' => 24.00,
                    'inbound_freight_allocated' => 1.00,
                ]);

                StockMovement::create([
                    'product_id' => $papelA4->id,
                    'user_id' => $comprasUser->id,
                    'type' => StockMovement::TYPE_PURCHASE_IN,
                    'quantity' => 50,
                    'stock_before' => max(0, $papelA4->stock - 50),
                    'stock_after' => $papelA4->stock,
                    'reference_type' => $po->getMorphClass(),
                    'reference_id' => $po->id,
                    'notes' => 'Recebimento NF-000142 (Papel & Suprimentos SP)',
                    'created_at' => now()->subDays(5),
                ]);
            }
        }

        if ($detergente = Product::where('slug', 'detergente-500ml-cx12')->first()) {
            SupplierProduct::updateOrCreate(
                ['supplier_id' => $supplierLimpeza->id, 'product_id' => $detergente->id],
                ['supplier_sku' => 'YPE-500-CX12', 'supplier_cost' => 22.00, 'lead_time_days' => 3, 'last_quoted_at' => now()]
            );
        }

        if ($cafe = Product::where('slug', 'cafe-500g')->first()) {
            SupplierProduct::updateOrCreate(
                ['supplier_id' => $supplierCopa->id, 'product_id' => $cafe->id],
                ['supplier_sku' => '3C-CAF-500', 'supplier_cost' => 18.00, 'lead_time_days' => 2, 'last_quoted_at' => now()]
            );
        }

        // 3. Empresas Clientes, Contratos, Carteiras e Equipe
        $ledger = app(WalletLedgerService::class);
        $businessPlan = Plan::where('slug', 'business')->firstOrFail();
        $corporatePlan = Plan::where('slug', 'corporate')->firstOrFail();

        $alfa = Company::updateOrCreate(
            ['cnpj' => '12.345.678/0001-90'],
            [
                'legal_name' => 'Alfa Soluções Corporativas Ltda',
                'trade_name' => 'Alfa Soluções',
                'state_registration' => '110.220.330.440',
                'email' => 'compras@alfaempresa.com.br',
                'phone' => '(11) 3000-1000',
                'billing_address' => 'Av. Paulista, 1578 - Bela Vista, São Paulo - SP',
                'billing_cep' => '01310-200',
                'status' => Company::STATUS_ACTIVE,
                'require_order_approval' => true,
                'low_balance_threshold' => 200.00,
            ]
        );

        $alfaGestor = $this->upsertCompanyUser('gestor@alfaempresa.com.br', 'Mariana Costa (Gestora Alfa)', $clientPlainPassword, $alfa, CompanyUser::ROLE_MANAGER, null, 'Diretoria Administrativa');
        $alfaSolicitante = $this->upsertCompanyUser('solicitante@alfaempresa.com.br', 'Lucas Oliveira (Solicitante Alfa)', $clientPlainPassword, $alfa, CompanyUser::ROLE_REQUESTER, 200.00, 'Copa & Facilities');

        Address::firstOrCreate(
            ['user_id' => $alfaGestor->id, 'company_id' => $alfa->id, 'cep' => '01310-200'],
            [
                'type' => 'delivery',
                'label' => 'Matriz Paulista',
                'recipient_name' => 'Recepção / Almoxarifado Alfa',
                'street' => 'Av. Paulista',
                'number' => '1578',
                'complement' => '8º andar',
                'neighborhood' => 'Bela Vista',
                'city' => 'São Paulo',
                'state' => 'SP',
                'is_default' => true,
            ]
        );

        $alfaWallet = $alfa->wallet()->firstOrFail();
        if (! $alfaWallet->transactions()->exists()) {
            $alfaContract = Contract::create([
                'company_id' => $alfa->id,
                'plan_id' => $businessPlan->id,
                'contracted_amount' => 1000.00,
                'credits_granted' => 1000.00,
                'status' => Contract::STATUS_ACTIVE,
                'terms_version' => (string) config('nexosupri.terms_version', 'Modelo-A-v1'),
                'accepted_terms_at' => now()->subDays(10),
                'accepted_by_user_id' => $alfaGestor->id,
                'starts_at' => now()->subDays(10),
            ]);

            $alfaPayment = Payment::create([
                'company_id' => $alfa->id,
                'credit_wallet_id' => $alfaWallet->id,
                'contract_id' => $alfaContract->id,
                'requested_by_user_id' => $alfaGestor->id,
                'payment_method' => 'pix_dynamic',
                'txid' => 'NSPXSEEDALFA000000000001',
                'amount' => 1000.00,
                'approved_amount' => 1000.00,
                'status' => Payment::STATUS_APPROVED,
                'admin_notes' => 'Liquidação inicial Plano Business',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(10),
            ]);

            $ledger->credit(
                lockedWallet: $alfaWallet,
                amountCents: CartPricing::toCents(1000.00),
                type: CreditTransaction::TYPE_CREDIT_PURCHASE,
                description: 'Contratação Plano Business (1.000 créditos)',
                reference: $alfaPayment,
                idempotencyKey: "seed:payment:{$alfaPayment->id}",
                actorUserId: $alfaGestor->id,
                reason: 'Conversão Modelo A (1 crédito = R$ 1,00)',
            );
        }

        // Pedido entregue inicial da Alfa para povoar relatórios e histórico
        $papel = Product::where('slug', 'papel-a4-500-folhas')->first();
        $cafeItem = Product::where('slug', 'cafe-500g')->first();
        if ($papel && $cafeItem && ! Order::where('idempotency_key', 'seed-alfa-delivered-order')->exists()) {
            $subtotal = round(((float) $papel->price * 3) + ((float) $cafeItem->price * 2), 2);
            $costSnapshot = round(($papel->total_unit_cost * 3) + ($cafeItem->total_unit_cost * 2), 2);
            $dasSnapshot = round($subtotal * 0.04, 2);
            $marginSnapshot = round($subtotal - $costSnapshot - $dasSnapshot, 2);

            $deliveredOrder = Order::create([
                'company_id' => $alfa->id,
                'user_id' => $alfaGestor->id,
                'approved_by_user_id' => $alfaGestor->id,
                'approved_at' => now()->subDays(4),
                'idempotency_key' => 'seed-alfa-delivered-order',
                'subtotal_amount' => $subtotal,
                'discount_amount' => 0.00,
                'shipping_cost' => 0.00,
                'total_amount' => $subtotal,
                'credits_debited' => $subtotal,
                'total_cost_snapshot' => $costSnapshot,
                'estimated_das_amount' => $dasSnapshot,
                'estimated_margin_amount' => $marginSnapshot,
                'status' => Order::STATUS_DELIVERED,
                'shipping_address' => 'Av. Paulista, 1578, 8º andar — Bela Vista, São Paulo - SP — CEP 01310-200',
                'shipping_cep' => '01310-200',
                'tracking_code' => 'NXSP20260001BR',
                'department' => 'Diretoria Administrativa',
                'delivered_at' => now()->subDays(2),
            ]);

            OrderItem::create([
                'order_id' => $deliveredOrder->id,
                'product_id' => $papel->id,
                'product_name' => $papel->name,
                'product_sku' => $papel->sku,
                'unit_presentation' => $papel->presentation,
                'quantity' => 3,
                'unit_price' => $papel->price,
                'unit_cost_snapshot' => $papel->total_unit_cost,
                'das_rate_snapshot' => $papel->das_rate,
                'discount_allocated' => 0.00,
                'item_status' => Order::STATUS_DELIVERED,
            ]);

            OrderItem::create([
                'order_id' => $deliveredOrder->id,
                'product_id' => $cafeItem->id,
                'product_name' => $cafeItem->name,
                'product_sku' => $cafeItem->sku,
                'unit_presentation' => $cafeItem->presentation,
                'quantity' => 2,
                'unit_price' => $cafeItem->price,
                'unit_cost_snapshot' => $cafeItem->total_unit_cost,
                'das_rate_snapshot' => $cafeItem->das_rate,
                'discount_allocated' => 0.00,
                'item_status' => Order::STATUS_DELIVERED,
            ]);

            OrderStatusHistory::create([
                'order_id' => $deliveredOrder->id,
                'from_status' => null,
                'to_status' => Order::STATUS_REQUESTED,
                'changed_by' => $alfaGestor->id,
                'notes' => 'Pedido confirmado com débito em créditos.',
                'created_at' => now()->subDays(4),
            ]);

            OrderStatusHistory::create([
                'order_id' => $deliveredOrder->id,
                'from_status' => Order::STATUS_REQUESTED,
                'to_status' => Order::STATUS_DELIVERED,
                'changed_by' => $admin->id,
                'notes' => 'Entrega concluída na recepção corporativa.',
                'created_at' => now()->subDays(2),
            ]);

            Delivery::create([
                'order_id' => $deliveredOrder->id,
                'status' => Delivery::STATUS_DELIVERED,
                'carrier' => 'Frota NexoSupri SP',
                'tracking_code' => 'NXSP20260001BR',
                'scheduled_route' => 'Rota Centro / Paulista',
                'receiver_name' => 'Mariana Costa',
                'delivered_at' => now()->subDays(2),
            ]);

            $ledger->debit(
                lockedWallet: $alfaWallet->fresh(),
                amountCents: CartPricing::toCents($subtotal),
                type: CreditTransaction::TYPE_ORDER_DEBIT,
                description: 'Compra corporativa #PED-'.str_pad((string) $deliveredOrder->id, 6, '0', STR_PAD_LEFT),
                reference: $deliveredOrder,
                idempotencyKey: "order:{$deliveredOrder->id}:debit",
                actorUserId: $alfaGestor->id,
                reason: 'Consumo inicial de abastecimento',
            );
        }

        // Pedido pendente de aprovação criado pelo solicitante da Alfa
        if ($copo = Product::where('slug', 'copo-descartavel-180ml')->first()) {
            if (! Order::where('idempotency_key', 'seed-alfa-pending-approval')->exists()) {
                $pendingSubtotal = round((float) $copo->price * 5, 2);
                $pendingCost = round($copo->total_unit_cost * 5, 2);
                $pendingDas = round($pendingSubtotal * 0.04, 2);

                $pendingOrder = Order::create([
                    'company_id' => $alfa->id,
                    'user_id' => $alfaSolicitante->id,
                    'idempotency_key' => 'seed-alfa-pending-approval',
                    'subtotal_amount' => $pendingSubtotal,
                    'discount_amount' => 0.00,
                    'shipping_cost' => 0.00,
                    'total_amount' => $pendingSubtotal,
                    'credits_debited' => 0.00,
                    'total_cost_snapshot' => $pendingCost,
                    'estimated_das_amount' => $pendingDas,
                    'estimated_margin_amount' => round($pendingSubtotal - $pendingCost - $pendingDas, 2),
                    'status' => Order::STATUS_AWAITING_APPROVAL,
                    'shipping_address' => 'Av. Paulista, 1578, 8º andar — Bela Vista, São Paulo - SP — CEP 01310-200',
                    'shipping_cep' => '01310-200',
                    'department' => 'Copa & Facilities',
                    'notes' => 'Reposição semanal de copos para copa.',
                ]);

                OrderItem::create([
                    'order_id' => $pendingOrder->id,
                    'product_id' => $copo->id,
                    'product_name' => $copo->name,
                    'product_sku' => $copo->sku,
                    'unit_presentation' => $copo->presentation,
                    'quantity' => 5,
                    'unit_price' => $copo->price,
                    'unit_cost_snapshot' => $copo->total_unit_cost,
                    'das_rate_snapshot' => $copo->das_rate,
                    'discount_allocated' => 0.00,
                    'item_status' => Order::STATUS_AWAITING_APPROVAL,
                ]);

                OrderStatusHistory::create([
                    'order_id' => $pendingOrder->id,
                    'from_status' => null,
                    'to_status' => Order::STATUS_AWAITING_APPROVAL,
                    'changed_by' => $alfaSolicitante->id,
                    'notes' => 'Aguardando aprovação do gestor da Alfa Soluções.',
                    'created_at' => now()->subHours(6),
                ]);
            }
        }

        // Segunda empresa cliente: Beta Logística
        $beta = Company::updateOrCreate(
            ['cnpj' => '98.765.432/0001-10'],
            [
                'legal_name' => 'Beta Logística e Serviços S.A.',
                'trade_name' => 'Beta Logística',
                'state_registration' => '220.330.440.550',
                'email' => 'suprimentos@betaempresa.com.br',
                'phone' => '(11) 3555-2200',
                'billing_address' => 'Rua Vergueiro, 2100 - Vila Mariana, São Paulo - SP',
                'billing_cep' => '04102-000',
                'status' => Company::STATUS_ACTIVE,
                'require_order_approval' => false,
                'low_balance_threshold' => 300.00,
            ]
        );

        $betaGestor = $this->upsertCompanyUser('gestor@betaempresa.com.br', 'Rafael Mendes (Gestor Beta)', $clientPlainPassword, $beta, CompanyUser::ROLE_MANAGER, null, 'Suprimentos');
        $betaWallet = $beta->wallet()->firstOrFail();

        if (! $betaWallet->transactions()->exists()) {
            $betaContract = Contract::create([
                'company_id' => $beta->id,
                'plan_id' => $corporatePlan->id,
                'contracted_amount' => 2500.00,
                'credits_granted' => 2500.00,
                'status' => Contract::STATUS_ACTIVE,
                'terms_version' => (string) config('nexosupri.terms_version', 'Modelo-A-v1'),
                'accepted_terms_at' => now()->subDays(5),
                'accepted_by_user_id' => $betaGestor->id,
                'starts_at' => now()->subDays(5),
            ]);

            $betaPayment = Payment::create([
                'company_id' => $beta->id,
                'credit_wallet_id' => $betaWallet->id,
                'contract_id' => $betaContract->id,
                'requested_by_user_id' => $betaGestor->id,
                'payment_method' => 'pix_dynamic',
                'txid' => 'NSPXSEEDBETA000000000001',
                'amount' => 2500.00,
                'approved_amount' => 2500.00,
                'status' => Payment::STATUS_APPROVED,
                'admin_notes' => 'Liquidação inicial Plano Corporate',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(5),
            ]);

            $ledger->credit(
                lockedWallet: $betaWallet,
                amountCents: CartPricing::toCents(2500.00),
                type: CreditTransaction::TYPE_CREDIT_PURCHASE,
                description: 'Contratação Plano Corporate (2.500 créditos)',
                reference: $betaPayment,
                idempotencyKey: "seed:payment:{$betaPayment->id}",
                actorUserId: $betaGestor->id,
                reason: 'Conversão Modelo A (1 crédito = R$ 1,00)',
            );
        }

        // 4. Cupons corporativos + avaliações e perguntas técnicas
        Coupon::updateOrCreate(
            ['code' => 'NEXO5'],
            [
                'label' => '5% OFF em pedidos corporativos acima de R$ 500,00',
                'type' => Coupon::TYPE_PERCENT,
                'value' => 5.00,
                'min_total' => 500.00,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(6),
                'max_uses' => 200,
                'per_user_limit' => 3,
                'is_active' => true,
            ]
        );

        Coupon::updateOrCreate(
            ['code' => 'SETUP15'],
            [
                'label' => '15% OFF em kits de escritório acima de R$ 2.500,00',
                'type' => Coupon::TYPE_PERCENT,
                'value' => 15.00,
                'min_total' => 2500.00,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(6),
                'max_uses' => 100,
                'per_user_limit' => 2,
                'is_active' => true,
            ]
        );

        Coupon::updateOrCreate(
            ['code' => 'TECH100'],
            [
                'label' => 'R$ 100 OFF em tecnologia e equipamentos acima de R$ 1.500,00',
                'type' => Coupon::TYPE_FIXED,
                'value' => 100.00,
                'min_total' => 1500.00,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(6),
                'max_uses' => 150,
                'per_user_limit' => 2,
                'is_active' => true,
            ]
        );

        if ($papel) {
            ProductReview::updateOrCreate(
                ['product_id' => $papel->id, 'user_id' => $alfaGestor->id],
                [
                    'rating' => 5,
                    'comment' => 'Resma padrão excelente para impressoras corporativas e entrega pontual pelo portal NexoSupri.',
                    'is_verified_purchase' => true,
                ]
            );

            ProductQuestion::updateOrCreate(
                ['product_id' => $papel->id, 'user_id' => $alfaSolicitante->id, 'question' => 'O papel A4 atende impressão frente e verso em impressora laser de alta velocidade?'],
                [
                    'answer' => 'Sim! Gramatura 75 g/m² com corte preciso e baixa geração de pó para uso intenso em multifuncionais corporativas.',
                    'answered_by' => $admin->id,
                    'answered_at' => now()->subDays(2),
                    'is_public' => true,
                ]
            );
        }

        if ($generatedAdminPassword) {
            $this->command?->warn("[SEED LOCAL] Senha gerada para equipe backoffice (admin@nexosupri.com.br): {$adminPlainPassword}");
        }

        if ($generatedClientPassword) {
            $this->command?->warn("[SEED LOCAL] Senha gerada para empresas (gestor@alfaempresa.com.br / solicitante@alfaempresa.com.br / gestor@betaempresa.com.br): {$clientPlainPassword}");
        }
    }

    private function upsertBackofficeUser(
        string $email,
        string $name,
        string $plainPassword,
        UserRole $role,
        bool $isAdmin,
    ): User {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($plainPassword),
            ]
        );

        $user->forceFill([
            'is_admin' => $isAdmin,
            'role' => $role->value,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    private function upsertCompanyUser(
        string $email,
        string $name,
        string $plainPassword,
        Company $company,
        string $companyRole,
        ?float $spendingLimit,
        string $department,
    ): User {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($plainPassword),
            ]
        );

        $user->forceFill([
            'is_admin' => false,
            'role' => $companyRole === CompanyUser::ROLE_MANAGER
                ? UserRole::CompanyManager->value
                : UserRole::CompanyRequester->value,
            'email_verified_at' => now(),
        ])->save();

        CompanyUser::updateOrCreate(
            ['company_id' => $company->id, 'user_id' => $user->id],
            [
                'company_role' => $companyRole,
                'spending_limit' => $spendingLimit,
                'department' => $department,
                'is_active' => true,
            ]
        );

        return $user;
    }
}
