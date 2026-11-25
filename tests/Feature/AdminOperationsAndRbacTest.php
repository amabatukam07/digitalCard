<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminOperationsAndRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchasing_role_manages_suppliers_purchase_orders_and_stock_but_is_blocked_from_finance(): void
    {
        $purchasing = User::factory()->purchasing()->create();
        $company = Company::factory()->create();
        $requester = User::factory()->forCompany($company)->create();
        $payment = Payment::create([
            'company_id' => $company->id,
            'credit_wallet_id' => $company->wallet->id,
            'requested_by_user_id' => $requester->id,
            'payment_method' => 'receipt',
            'amount' => 500.00,
            'status' => Payment::STATUS_PENDING,
        ]);

        // Purchasing não pode aprovar pagamentos nem criar empresas
        $this->actingAs($purchasing)->patch(route('admin.payments.approve', $payment))->assertForbidden();
        $this->actingAs($purchasing)->post(route('admin.companies.store'), [
            'cnpj' => '55.666.777/0001-88',
            'legal_name' => 'Teste Ltda',
            'trade_name' => 'Teste',
            'email' => 'teste@empresa.com',
        ])->assertForbidden();

        // Purchasing cadastra fornecedor, produto e ordem de compra
        $this->actingAs($purchasing)->post(route('admin.suppliers.store'), [
            'cnpj' => '55.666.777/0001-88',
            'legal_name' => 'Distribuidora Nexo Ltda',
            'trade_name' => 'Distribuidora Nexo',
            'lead_time_days' => 3,
        ])->assertSessionHas('success');

        $supplier = Supplier::where('cnpj', '55.666.777/0001-88')->firstOrFail();
        $category = Category::create(['name' => 'Limpeza', 'slug' => 'limpeza-rbac']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'cost_price' => 10.00,
            'inbound_freight' => 0.00,
            'stock' => 5,
            'min_stock' => 5,
        ]);

        $this->actingAs($purchasing)->post(route('admin.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'unit_cost' => 12.00,
            'freight_amount' => 20.00,
        ])->assertSessionHas('success');

        $po = PurchaseOrder::firstOrFail();
        $this->assertEquals(260.00, (float) $po->total_amount);

        // Recebimento da OC atualiza estoque, custo e registra StockMovement
        $this->actingAs($purchasing)->patch(route('admin.purchase-orders.receive', $po), [
            'invoice_number' => 'NF-998877',
            'update_product_cost' => '1',
        ])->assertSessionHas('success');

        $freshProduct = $product->fresh();
        $this->assertEquals(25, $freshProduct->stock);
        $this->assertEquals(12.00, (float) $freshProduct->cost_price);
        $this->assertEquals(1.00, (float) $freshProduct->inbound_freight);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_PURCHASE_IN,
            'quantity' => 20,
        ]);

        // Ajuste manual de inventário
        $this->actingAs($purchasing)->post(route('admin.inventory.adjust', $product), [
            'quantity_delta' => -3,
            'notes' => 'Avaria conferida no inventário físico',
        ])->assertSessionHas('success');

        $this->assertEquals(22, $product->fresh()->stock);
    }

    public function test_auditor_role_has_read_only_access_to_admin_screens_and_csvs_but_cannot_mutate(): void
    {
        $this->seed(PlanSeeder::class);
        $auditor = User::factory()->auditor()->create();
        $company = Company::factory()->create();
        $plan = Plan::where('slug', 'essencial')->firstOrFail();
        $order = Order::create([
            'company_id' => $company->id,
            'user_id' => User::factory()->forCompany($company)->create()->id,
            'subtotal_amount' => 100.00,
            'discount_amount' => 0.00,
            'shipping_cost' => 0.00,
            'total_amount' => 100.00,
            'credits_debited' => 100.00,
            'total_cost_snapshot' => 60.00,
            'estimated_das_amount' => 4.00,
            'estimated_margin_amount' => 36.00,
            'status' => Order::STATUS_REQUESTED,
            'shipping_address' => 'Av. Paulista, 100',
        ]);

        foreach ([
            route('admin.dashboard'),
            route('admin.companies.index'),
            route('admin.plans.index'),
            route('admin.payments.index'),
            route('admin.suppliers.index'),
            route('admin.orders.index'),
            route('admin.reports.sales.csv'),
            route('admin.reports.credits.csv'),
        ] as $readUrl) {
            $this->actingAs($auditor)->get($readUrl)->assertOk();
        }

        // Auditor não pode alterar planos, status de empresa ou entregar pedido
        $this->actingAs($auditor)->patch(route('admin.plans.update', $plan), [
            'name' => 'Essencial Alterado',
            'price' => 600,
            'credits_amount' => 600,
            'target_profile' => 'Teste',
        ])->assertForbidden();

        $this->actingAs($auditor)->patch(route('admin.companies.status', $company), [
            'status' => 'suspended',
        ])->assertForbidden();

        $this->actingAs($auditor)->patch(route('admin.orders.deliver', $order))->assertForbidden();
    }

    public function test_logistics_and_commercial_roles_enforce_boundaries_correctly(): void
    {
        $this->seed(PlanSeeder::class);
        $logistics = User::factory()->logistics()->create();
        $commercial = User::factory()->commercial()->create();
        $company = Company::factory()->create();
        $requester = User::factory()->forCompany($company)->create();
        $plan = Plan::where('slug', 'essencial')->firstOrFail();

        $payment = Payment::create([
            'company_id' => $company->id,
            'credit_wallet_id' => $company->wallet->id,
            'requested_by_user_id' => $requester->id,
            'payment_method' => 'receipt',
            'amount' => 500.00,
            'status' => Payment::STATUS_PENDING,
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'user_id' => $requester->id,
            'subtotal_amount' => 150.00,
            'discount_amount' => 0.00,
            'shipping_cost' => 0.00,
            'total_amount' => 150.00,
            'credits_debited' => 150.00,
            'total_cost_snapshot' => 90.00,
            'estimated_das_amount' => 6.00,
            'estimated_margin_amount' => 54.00,
            'status' => Order::STATUS_REQUESTED,
            'shipping_address' => 'Av. Paulista, 100',
        ]);

        // Logística entrega pedido, mas não aprova pagamento
        $this->actingAs($logistics)->patch(route('admin.orders.deliver', $order))->assertSessionHas('success');
        $this->actingAs($logistics)->patch(route('admin.payments.approve', $payment))->assertForbidden();

        // Comercial atualiza plano e suspende/reativa empresa
        $this->actingAs($commercial)->patch(route('admin.plans.update', $plan), [
            'name' => 'Essencial',
            'price' => 550.00,
            'credits_amount' => 550.00,
            'target_profile' => 'Microempresas',
            'is_active' => '1',
        ])->assertSessionHas('success');

        $this->assertEquals(550.00, (float) $plan->fresh()->price);

        $this->actingAs($commercial)->patch(route('admin.companies.status', $company), [
            'status' => 'suspended',
        ])->assertSessionHas('success');

        $this->assertSame('suspended', $company->fresh()->status);
    }

    public function test_admin_product_gallery_upload_creates_multiple_product_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $category = Category::create(['name' => 'Suprimentos Galeria', 'slug' => 'suprimentos-galeria']);

        $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $files = [
            UploadedFile::fake()->createWithContent('foto1.png', $tinyPng),
            UploadedFile::fake()->createWithContent('foto2.png', $tinyPng),
            UploadedFile::fake()->createWithContent('foto3.png', $tinyPng),
        ];

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Kit Pasta Catálogo Galeria',
            'category_id' => $category->id,
            'unit' => 'kit',
            'presentation' => 'Kit 10 pastas',
            'description' => 'Conjunto de pastas com 3 imagens de galeria.',
            'cost_price' => 30.00,
            'inbound_freight' => 2.00,
            'markup_percent' => 35.0,
            'das_rate' => 4.0,
            'stock' => 15,
            'min_stock' => 3,
            'gallery_files' => $files,
        ])->assertSessionHas('success');

        $product = Product::where('slug', 'kit-pasta-catalogo-galeria')->firstOrFail();

        $this->assertSame(3, ProductImage::where('product_id', $product->id)->count());
        $this->assertCount(3, $product->gallerySlides());
    }
}
