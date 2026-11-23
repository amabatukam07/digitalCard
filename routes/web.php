<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\AdminRechargeController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\BundleController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\GiftCardController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PriceAlertController;
use App\Http\Controllers\ProductQuestionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Controllers\VirtualCardController;
use Illuminate\Support\Facades\Route;

// Catálogo Público e Sitemap SEO (V6)
Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
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

// CEP / Endereço Rápido, Cotação de Frete e Lista de Favoritos
Route::get('/frete/cotacao', [CatalogController::class, 'quoteShipping'])
    ->middleware('throttle:cep-lookup')
    ->name('catalog.shipping.quote');
Route::post('/endereco-entrega', [CatalogController::class, 'saveAddress'])
    ->middleware('throttle:cep-lookup')
    ->name('address.save');
Route::get('/favoritos', [CatalogController::class, 'wishlistIndex'])->name('wishlist.index');
Route::post('/favoritos/mover-para-carrinho', [CatalogController::class, 'moveWishlistToCart'])->name('wishlist.move-to-cart');
Route::post('/favoritos/{product}', [CatalogController::class, 'toggleWishlist'])->name('wishlist.toggle');

// Carrinho de Compras acessível para visitantes (sessão) e persistido para usuários autenticados
Route::get('/carrinho', [CartController::class, 'index'])->name('cart.index');
Route::post('/carrinho/adicionar/{product}', [CartController::class, 'add'])->name('cart.add');
Route::patch('/carrinho/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/carrinho/{product}', [CartController::class, 'remove'])->name('cart.remove');
Route::delete('/carrinho', [CartController::class, 'clear'])->name('cart.clear');
Route::post('/carrinho/cupom', [CartController::class, 'applyCoupon'])
    ->middleware('throttle:coupon-apply')
    ->name('cart.coupon.apply');
Route::delete('/carrinho/cupom', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

Route::redirect('/dashboard', '/')->middleware(['auth'])->name('dashboard');
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

// Rotas financeiras, cartão virtual, checkout e pedidos exigem e-mail verificado ('verified')
Route::middleware(['auth', 'verified'])->group(function (): void {
    // Cartão Virtual, PIX Simulado (com status JSON para polling B5), Revelação de CVV sob demanda e Recarga Privada
    Route::get('/meu-cartao', [VirtualCardController::class, 'show'])->name('card.show');
    Route::get('/meu-cartao/extrato.csv', [VirtualCardController::class, 'exportStatementCsv'])->name('card.statement.csv');
    Route::post('/meu-cartao/recarga', [VirtualCardController::class, 'storeRecharge'])
        ->middleware('throttle:receipt-upload')
        ->name('card.recharge');
    Route::post('/meu-cartao/pix/gerar', [VirtualCardController::class, 'generatePixCharge'])
        ->middleware('throttle:pix-generate')
        ->name('card.pix.generate');
    Route::get('/meu-cartao/pix/{recharge}/status', [VirtualCardController::class, 'checkPixStatus'])->name('card.pix.status');
    Route::post('/webhooks/pix/simular', [VirtualCardController::class, 'simulatePixWebhook'])->name('webhooks.pix.simulate');
    Route::post('/meu-cartao/revelar-cvv', [VirtualCardController::class, 'revealCvv'])->name('card.reveal-cvv');
    Route::post('/meu-cartao/rotacionar-cvv', [VirtualCardController::class, 'rotateCvv'])
        ->middleware('throttle:10,1')
        ->name('card.rotate-cvv');
    Route::get('/recargas/{recharge}/comprovante', [VirtualCardController::class, 'showReceipt'])->name('recharges.receipt');
    Route::patch('/meu-cartao/bloqueio', [VirtualCardController::class, 'toggleBlock'])->name('card.toggle-block');
    Route::patch('/meu-cartao/limite', [VirtualCardController::class, 'updateDailyLimit'])->name('card.update-limit');
    Route::patch('/meu-cartao/pin', [VirtualCardController::class, 'updatePin'])->name('card.update-pin');
    Route::post('/meu-cartao/transferir', [VirtualCardController::class, 'transfer'])
        ->middleware('throttle:wallet-transfer')
        ->name('card.transfer');
    Route::post('/vale-presente', [GiftCardController::class, 'store'])->name('gift-cards.store');
    Route::post('/vale-presente/resgatar', [GiftCardController::class, 'redeem'])->name('gift-cards.redeem');
    Route::post('/produto/{product:slug}/alerta-preco', [PriceAlertController::class, 'store'])->name('price-alerts.store');
    Route::delete('/alertas-preco/{priceAlert}', [PriceAlertController::class, 'destroy'])->name('price-alerts.destroy');
    Route::post('/meu-cartao/metas', [SavingsGoalController::class, 'store'])->name('goals.store');
    Route::post('/meu-cartao/metas/{goal}/depositar', [SavingsGoalController::class, 'deposit'])->name('goals.deposit');
    Route::post('/meu-cartao/metas/{goal}/resgatar', [SavingsGoalController::class, 'withdraw'])->name('goals.withdraw');
    Route::delete('/meu-cartao/metas/{goal}', [SavingsGoalController::class, 'destroy'])->name('goals.destroy');
    Route::post('/produto/{product:slug}/perguntas', [ProductQuestionController::class, 'store'])
        ->middleware('throttle:product-question')
        ->name('questions.store');

    // Compras, Acompanhamento de Pedidos e Direito de Arrependimento (7 dias)
    Route::post('/checkout', [OrderController::class, 'checkout'])
        ->middleware('throttle:checkout')
        ->name('orders.checkout');
    Route::get('/meus-pedidos', [OrderController::class, 'myOrders'])->name('orders.index');
    Route::get('/meus-pedidos/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/meus-pedidos/{order}/repetir', [OrderController::class, 'reorder'])->name('orders.reorder');
    Route::patch('/meus-pedidos/{order}/cancelar', [OrderController::class, 'cancelOrder'])->name('orders.cancel');
    Route::post('/meus-pedidos/{order}/arrependimento', [OrderController::class, 'requestRegretReturn'])->name('orders.return');

    // Avaliações Verificadas de Produtos
    Route::post('/produto/{product:slug}/avaliar', [CatalogController::class, 'storeReview'])->name('catalog.review');
});

// Rotas de Administração
Route::middleware(['auth', 'verified', 'can:admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/relatorios/vendas.csv', [AdminDashboardController::class, 'exportSalesCsv'])->name('reports.sales.csv');

    Route::get('/recargas', [AdminRechargeController::class, 'index'])->name('recharges.index');

    Route::patch('/recargas/{recharge}/aprovar', [VirtualCardController::class, 'approveRecharge'])
        ->name('recharges.approve');
    Route::patch('/recargas/{recharge}/recusar', [VirtualCardController::class, 'rejectRecharge'])
        ->name('recharges.reject');

    Route::get('/pedidos', [OrderController::class, 'adminIndex'])->name('orders.index');
    Route::patch('/pedidos/{order}/entregar', [OrderController::class, 'markAsDelivered'])
        ->name('orders.deliver');
    Route::patch('/pedidos/{order}/status', [OrderController::class, 'updateStatus'])
        ->name('orders.status');
    Route::patch('/pedidos/{order}/cancelar', [OrderController::class, 'adminCancelOrder'])
        ->name('orders.cancel');

    Route::post('/produtos', [AdminDashboardController::class, 'storeProduct'])
        ->name('products.store');
    Route::patch('/produtos/{product}', [AdminDashboardController::class, 'updateProduct'])
        ->name('products.update');
    Route::patch('/produtos/{product}/status', [AdminDashboardController::class, 'toggleProduct'])
        ->name('products.toggle');

    Route::post('/categorias', [AdminDashboardController::class, 'storeCategory'])
        ->name('categories.store');
    Route::patch('/categorias/{category}', [AdminDashboardController::class, 'updateCategory'])
        ->name('categories.update');
    Route::delete('/categorias/{category}', [AdminDashboardController::class, 'destroyCategory'])
        ->name('categories.destroy');

    Route::post('/cupons', [AdminDashboardController::class, 'storeCoupon'])
        ->name('coupons.store');
    Route::patch('/cupons/{coupon}/status', [AdminDashboardController::class, 'toggleCoupon'])
        ->name('coupons.toggle');

    Route::patch('/usuarios/{user}/role', [AdminDashboardController::class, 'updateUserRole'])
        ->name('users.role');

    Route::patch('/perguntas/{question}/responder', [ProductQuestionController::class, 'answer'])
        ->name('questions.answer');
});

require __DIR__.'/auth.php';
