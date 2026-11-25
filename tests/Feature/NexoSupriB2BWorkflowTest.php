<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Contract;
use App\Models\CreditTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NexoSupriB2BWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_plans_page_renders_five_plans_and_benefits_matrix(): void
    {
        $this->seed(PlanSeeder::class);

        $response = $this->get(route('plans.index'));

        $response->assertOk();
        $response->assertSee('Essencial');
        $response->assertSee('Business');
        $response->assertSee('Corporate');
        $response->assertSee('Premium');
        $response->assertSee('Enterprise');
        $response->assertSee('Acesso ao catálogo');
        $response->assertSee('Acompanhamento dedicado');
    }

    public function test_pix_plan_hire_requires_terms_and_credits_wallet_one_to_one_with_reconciliation(): void
    {
        $this->seed(PlanSeeder::class);

        $company = Company::factory()->create();
        $manager = User::factory()->forCompany($company)->create();
        $businessPlan = Plan::where('slug', 'business')->firstOrFail();

        // Sem aceite dos termos do Modelo A deve falhar validação
        $this->actingAs($manager)->post(route('wallet.plans.pix'), [
            'plan_id' => $businessPlan->id,
        ])->assertSessionHasErrors('accept_terms');

        // Com aceite gera cobrança PIX e contrato pendente
        $this->actingAs($manager)->post(route('wallet.plans.pix'), [
            'plan_id' => $businessPlan->id,
            'accept_terms' => '1',
        ])->assertSessionHas('success');

        $payment = Payment::firstOrFail();
        $this->assertEquals(1000.00, (float) $payment->amount);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertStringStartsWith('NSPX', (string) $payment->txid);

        // Simula liquidação via webhook e verifica conversão 1:1 (R$ 1.000,00 = 1.000 créditos)
        $this->actingAs($manager)->post(route('webhooks.pix.simulate'), [
            'txid' => $payment->txid,
        ])->assertSessionHas('success');

        $wallet = $company->wallet->fresh();
        $this->assertEquals(1000.00, (float) $wallet->balance);
        $this->assertEquals(1000.00, (float) $wallet->total_acquired);
        $this->assertSame(Payment::STATUS_APPROVED, $payment->fresh()->status);
        $this->assertSame(Contract::STATUS_ACTIVE, $payment->contract->fresh()->status);
        $this->assertDatabaseHas('credit_transactions', [
            'credit_wallet_id' => $wallet->id,
            'type' => CreditTransaction::TYPE_CREDIT_PURCHASE,
            'amount' => 1000.00,
        ]);

        $this->artisan('nexosupri:reconcile-ledgers')->assertExitCode(0);
    }

    public function test_receipt_upload_duplicate_rejection_self_approval_block_and_finance_approval(): void
    {
        $this->seed(PlanSeeder::class);
        Storage::fake('local');

        $company = Company::factory()->create();
        $manager = User::factory()->forCompany($company)->create();
        $financeUser = User::factory()->finance()->create();
        $plan = Plan::where('slug', 'essencial')->firstOrFail();

        $file = UploadedFile::fake()->create('comprovante-pix.pdf', 120, 'application/pdf');

        $this->actingAs($manager)->post(route('wallet.plans.receipt'), [
            'plan_id' => $plan->id,
            'accept_terms' => '1',
            'receipt' => $file,
        ])->assertSessionHas('success');

        $payment = Payment::firstOrFail();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);

        // Tentativa de enviar o mesmo arquivo duplicado deve ser rejeitada
        $duplicate = UploadedFile::fake()->createWithContent('duplicado.pdf', Storage::disk('local')->get($payment->receipt_path));
        $this->actingAs($manager)->post(route('wallet.plans.receipt'), [
            'plan_id' => $plan->id,
            'accept_terms' => '1',
            'receipt' => $duplicate,
        ])->assertSessionHasErrors('receipt');

        // Solicitante não pode aprovar o próprio pagamento (mesmo se tivesse permissão)
        $this->actingAs($manager)->patch(route('admin.payments.approve', $payment))
            ->assertForbidden();

        // Financeiro aprova e credita 1:1
        $this->actingAs($financeUser)->patch(route('admin.payments.approve', $payment), [
            'admin_notes' => 'TED conferida no extrato bancário.',
        ])->assertSessionHas('success');

        $this->assertEquals(500.00, (float) $company->wallet->fresh()->balance);
        $this->assertSame(Payment::STATUS_APPROVED, $payment->fresh()->status);
    }

    public function test_requester_order_requires_manager_approval_before_debit_and_tenant_isolation_holds(): void
    {
        $companyA = Company::factory()->create(['require_order_approval' => true]);
        $companyA->wallet()->update(['balance' => 1000.00, 'total_acquired' => 1000.00]);
        $managerA = User::factory()->forCompany($companyA, CompanyUser::ROLE_MANAGER)->create();
        $requesterA = User::factory()->forCompany($companyA, CompanyUser::ROLE_REQUESTER, 250.00, 'Copa')->create();

        $companyB = Company::factory()->create(['require_order_approval' => true]);
        $managerB = User::factory()->forCompany($companyB, CompanyUser::ROLE_MANAGER)->create();

        $category = Category::create(['name' => 'Copa', 'slug' => 'copa-b2b']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 120.00,
            'cost_price' => 70.00,
            'stock' => 10,
            'is_active' => true,
        ]);

        // Solicitante fecha pedido -> fica Aguardando aprovação sem debitar carteira nem baixar estoque
        $this->actingAs($requesterA)->post(route('orders.checkout'), [
            'shipping_address' => 'Av. Paulista, 1578 - São Paulo/SP',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame(Order::STATUS_AWAITING_APPROVAL, $order->status);
        $this->assertEquals(1000.00, (float) $companyA->wallet->fresh()->balance);
        $this->assertEquals(10, $product->fresh()->stock);

        // Solicitante não pode autoaprovar o pedido
        $this->actingAs($requesterA)->post(route('orders.approve', $order))->assertForbidden();

        // Gestor de outra empresa (Company B) é bloqueado (isolamento multiempresa)
        $this->actingAs($managerB)->get(route('orders.show', $order))->assertForbidden();
        $this->actingAs($managerB)->post(route('orders.approve', $order))->assertForbidden();

        // Gestor da Company A aprova -> debita 240 créditos e baixa 2 unidades do estoque
        $this->actingAs($managerA)->post(route('orders.approve', $order))
            ->assertSessionHas('success');

        $approvedOrder = $order->fresh();
        $this->assertSame(Order::STATUS_REQUESTED, $approvedOrder->status);
        $this->assertEquals(240.00, (float) $approvedOrder->credits_debited);
        $this->assertEquals(760.00, (float) $companyA->wallet->fresh()->balance);
        $this->assertEquals(8, $product->fresh()->stock);
    }
}
