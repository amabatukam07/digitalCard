<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\AdminCompanyController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminSupplierController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\BundleController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CompanyDashboardController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\CreditWalletController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PriceAlertController;
use App\Http\Controllers\ProductQuestionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Catálogo Público, Planos Comerciais e Sitemap SEO
Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/planos', [PlanController::class, 'index'])->name('plans.index');
Route::get('/sitemap.xml', [CatalogController::class, 'sitemap'])->name('sitemap');
Route::get('/categoria/{categorySlug}', [CatalogController::class, 'index'])->name('catalog.category');
Route::get('/produto/{product:slug}', [CatalogController::class, 'show'])->name('catalog.product');
Route::get('/busca/sugestoes', [CatalogController::class, 'suggestions'])
    ->middleware('throttle:search-suggest')
    ->name('catalog.suggestions');
Route::get('/comparar', [CompareController::class, 'index'])->name('compare.index');
Route::post('/comparar/{product}', [CompareController::class, 'toggle'])->name('compare.toggle');
Route::delete('/comparar/{product}', [CompareController::class, 'remove'])->name('compare.remove');
Route::delete('/comparar', [CompareController::class, 'clear'])->name('compare.clear');
Route::get('/kits', [BundleController::class, 'index'])->name('bundles.index');
Route::post('/kits/{bundle:slug}/adicionar', [BundleController::class, 'addToCart'])->name('bundles.add-to-cart');

// CEP / Endereço Rápido, Cotação de Frete e Itens Homologados
Route::get('/frete/cotacao', [CatalogController::class, 'quoteShipping'])
    ->middleware('throttle:cep-lookup')
    ->name('catalog.shipping.quote');
Route::post('/endereco-entrega', [CatalogController::class, 'saveAddress'])
    ->middleware('throttle:cep-lookup')
    ->name('address.save');
Route::get('/favoritos', [CatalogController::class, 'wishlistIndex'])->name('wishlist.index');
Route::post('/favoritos/mover-para-carrinho', [CatalogController::class, 'moveWishlistToCart'])->name('wishlist.move-to-cart');
Route::post('/favoritos/{product}', [CatalogController::class, 'toggleWishlist'])->name('wishlist.toggle');

// Carrinho de Compras B2B
Route::get('/carrinho', [CartController::class, 'index'])->name('cart.index');
Route::post('/carrinho/adicionar/{product}', [CartController::class, 'add'])->name('cart.add');
Route::patch('/carrinho/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/carrinho/{product}', [CartController::class, 'remove'])->name('cart.remove');
Route::delete('/carrinho', [CartController::class, 'clear'])->name('cart.clear');
Route::post('/carrinho/cupom', [CartController::class, 'applyCoupon'])
    ->middleware('throttle:coupon-apply')
    ->name('cart.coupon.apply');
Route::delete('/carrinho/cupom', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

Route::get('/dashboard', function (Request $request): RedirectResponse {
    if ($request->user()?->hasAdminAccess() && $request->user()?->primaryCompany() === null) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route('company.dashboard');
})->middleware(['auth'])->name('dashboard');
Route::get('/health', HealthCheckController::class)->name('health');

// Rotas Autenticadas de Perfil, Endereços e Notificações
Route::middleware(['auth'])->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/enderecos', [AddressController::class, 'store'])->name('addresses.store');
    Route::patch('/enderecos/{address}/padrao', [AddressController::class, 'setDefault'])->name('addresses.default');
    Route::delete('/enderecos/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');

    Route::post('/notificacoes/marcar-lidas', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});

// Portal da Empresa Cliente: Dashboard, Carteira de Créditos, Planos, Pedidos e Aprovações
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/minha-empresa', [CompanyDashboardController::class, 'index'])->name('company.dashboard');
    Route::post('/minha-empresa/usuarios', [CompanyDashboardController::class, 'storeMember'])->name('company.members.store');
    Route::patch('/minha-empresa/usuarios/{companyUser}', [CompanyDashboardController::class, 'updateMember'])->name('company.members.update');
    Route::patch('/minha-empresa/configuracoes', [CompanyDashboardController::class, 'updateSettings'])->name('company.settings.update');

    Route::get('/carteira', [CreditWalletController::class, 'show'])->name('wallet.show');
    Route::redirect('/meu-cartao', '/carteira')->name('card.legacy');
    Route::get('/carteira/extrato.csv', [CreditWalletController::class, 'exportStatementCsv'])->name('wallet.statement.csv');
    Route::post('/carteira/planos/pix', [CreditWalletController::class, 'hirePlanWithPix'])
        ->middleware('throttle:pix-generate')
        ->name('wallet.plans.pix');
    Route::post('/carteira/planos/comprovante', [CreditWalletController::class, 'hirePlanWithReceipt'])
        ->middleware('throttle:receipt-upload')
        ->name('wallet.plans.receipt');
    Route::get('/carteira/pix/{payment}/status', [CreditWalletController::class, 'checkPixStatus'])->name('wallet.pix.status');
    Route::post('/webhooks/pix/simular', [CreditWalletController::class, 'simulatePixWebhook'])->name('webhooks.pix.simulate');
    Route::get('/pagamentos/{payment}/comprovante', [CreditWalletController::class, 'showReceipt'])->name('payments.receipt');
    Route::patch('/carteira/bloqueio', [CreditWalletController::class, 'toggleBlock'])->name('wallet.toggle-block');
    Route::patch('/carteira/limite', [CreditWalletController::class, 'updateDailyLimit'])->name('wallet.update-limit');

    Route::post('/produto/{product:slug}/alerta-preco', [PriceAlertController::class, 'store'])->name('price-alerts.store');
    Route::delete('/alertas-preco/{priceAlert}', [PriceAlertController::class, 'destroy'])->name('price-alerts.destroy');
    Route::post('/produto/{product:slug}/perguntas', [ProductQuestionController::class, 'store'])
        ->middleware('throttle:product-question')
        ->name('questions.store');

    Route::post('/checkout', [OrderController::class, 'checkout'])
        ->middleware('throttle:checkout')
        ->name('orders.checkout');
    Route::get('/meus-pedidos', [OrderController::class, 'myOrders'])->name('orders.index');
    Route::get('/meus-pedidos/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/meus-pedidos/{order}/aprovar', [OrderController::class, 'approveOrder'])->name('orders.approve');
    Route::post('/meus-pedidos/{order}/repetir', [OrderController::class, 'reorder'])->name('orders.reorder');
    Route::patch('/meus-pedidos/{order}/cancelar', [OrderController::class, 'cancelOrder'])->name('orders.cancel');
    Route::post('/meus-pedidos/{order}/arrependimento', [OrderController::class, 'requestRegretReturn'])->name('orders.return');

    Route::post('/produto/{product:slug}/avaliar', [CatalogController::class, 'storeReview'])->name('catalog.review');
});

// Backoffice Administrativo NexoSupri
Route::middleware(['auth', 'verified', 'can:admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/relatorios/vendas.csv', [AdminDashboardController::class, 'exportSalesCsv'])->name('reports.sales.csv');
    Route::get('/relatorios/creditos.csv', [AdminDashboardController::class, 'exportCompanyCreditsCsv'])->name('reports.credits.csv');

    Route::get('/empresas', [AdminCompanyController::class, 'index'])->name('companies.index');
    Route::post('/empresas', [AdminCompanyController::class, 'store'])->name('companies.store');
    Route::patch('/empresas/{company}/status', [AdminCompanyController::class, 'updateStatus'])->name('companies.status');

    Route::get('/planos', [AdminPlanController::class, 'index'])->name('plans.index');
    Route::patch('/planos/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');

    Route::get('/pagamentos', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::patch('/pagamentos/{payment}/aprovar', [CreditWalletController::class, 'approvePayment'])->name('payments.approve');
    Route::patch('/pagamentos/{payment}/recusar', [CreditWalletController::class, 'rejectPayment'])->name('payments.reject');

    Route::get('/fornecedores', [AdminSupplierController::class, 'index'])->name('suppliers.index');
    Route::post('/fornecedores', [AdminSupplierController::class, 'store'])->name('suppliers.store');
    Route::post('/fornecedores/vincular-produto', [AdminSupplierController::class, 'linkProduct'])->name('suppliers.link-product');
    Route::post('/fornecedores/compras', [AdminSupplierController::class, 'storePurchaseOrder'])->name('purchase-orders.store');
    Route::patch('/fornecedores/compras/{purchaseOrder}/receber', [AdminSupplierController::class, 'receivePurchaseOrder'])->name('purchase-orders.receive');
    Route::post('/estoque/{product}/ajuste', [AdminSupplierController::class, 'adjustStock'])->name('inventory.adjust');

    Route::get('/pedidos', [OrderController::class, 'adminIndex'])->name('orders.index');
    Route::patch('/pedidos/{order}/entregar', [OrderController::class, 'markAsDelivered'])->name('orders.deliver');
    Route::patch('/pedidos/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    Route::patch('/pedidos/{order}/cancelar', [OrderController::class, 'adminCancelOrder'])->name('orders.cancel');

    Route::post('/produtos', [AdminDashboardController::class, 'storeProduct'])->name('products.store');
    Route::patch('/produtos/{product}', [AdminDashboardController::class, 'updateProduct'])->name('products.update');
    Route::patch('/produtos/{product}/status', [AdminDashboardController::class, 'toggleProduct'])->name('products.toggle');

    Route::post('/categorias', [AdminDashboardController::class, 'storeCategory'])->name('categories.store');
    Route::patch('/categorias/{category}', [AdminDashboardController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/categorias/{category}', [AdminDashboardController::class, 'destroyCategory'])->name('categories.destroy');

    Route::post('/cupons', [AdminDashboardController::class, 'storeCoupon'])->name('coupons.store');
    Route::patch('/cupons/{coupon}/status', [AdminDashboardController::class, 'toggleCoupon'])->name('coupons.toggle');

    Route::patch('/usuarios/{user}/role', [AdminDashboardController::class, 'updateUserRole'])->name('users.role');
    Route::patch('/perguntas/{question}/responder', [ProductQuestionController::class, 'answer'])->name('questions.answer');
});

require __DIR__.'/auth.php';
