<?php

namespace Database\Seeders;

use App\Models\CardTransaction;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ProductReview;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Models\VirtualCard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Catálogo base de Categorias e Produtos (45 produtos)
        $this->call(CatalogSeeder::class);

        // 2. Contas iniciais e saldos de desenvolvimento APENAS em ambiente local ou de testes (S4)
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->info('Ambiente de produção detectado: contas iniciais e saldos de desenvolvimento foram ignorados por segurança.');

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

        // 3. Usuário Administrador (Desenvolvimento/Testes)
        $admin = User::updateOrCreate(
            ['email' => 'admin@digitalcard.com'],
            [
                'name' => 'Administrador DigitalCard',
                'password' => Hash::make($adminPlainPassword),
            ]
        );
        $admin->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();

        $adminCard = $admin->virtualCard()->firstOrCreate([], [
            'card_number' => VirtualCard::generateUniqueCardNumber(),
            'cvv' => VirtualCard::generateCvv(),
            'expiration_date' => VirtualCard::DEFAULT_EXPIRATION_DATE,
            'balance' => 0.00,
            'cashback_earned' => 0.00,
        ]);

        $adminCard->update([
            'balance' => 2500.00,
            'cashback_earned' => 120.00,
            'security_pin' => Hash::make('1234'),
        ]);

        CardTransaction::firstOrCreate(
            [
                'virtual_card_id' => $adminCard->id,
                'description' => 'Saldo inicial da carteira (Administrador)',
            ],
            [
                'type' => CardTransaction::TYPE_RECHARGE,
                'amount' => 2500.00,
                'balance_after' => 2500.00,
            ]
        );

        // 4. Usuário Cliente (Desenvolvimento/Testes)
        $client = User::updateOrCreate(
            ['email' => 'cliente@digitalcard.com'],
            [
                'name' => 'Cliente DigitalCard',
                'password' => Hash::make($clientPlainPassword),
            ]
        );
        $client->forceFill(['is_admin' => false, 'email_verified_at' => now()])->save();

        $clientCard = $client->virtualCard()->firstOrCreate([], [
            'card_number' => VirtualCard::generateUniqueCardNumber(),
            'cvv' => VirtualCard::generateCvv(),
            'expiration_date' => VirtualCard::DEFAULT_EXPIRATION_DATE,
            'balance' => 0.00,
            'cashback_earned' => 0.00,
        ]);

        $clientCard->update([
            'balance' => 7500.00,
            'cashback_earned' => 185.50,
            'security_pin' => Hash::make('1234'),
        ]);

        CardTransaction::firstOrCreate(
            [
                'virtual_card_id' => $clientCard->id,
                'description' => 'Saldo inicial da carteira (Cliente)',
            ],
            [
                'type' => CardTransaction::TYPE_RECHARGE,
                'amount' => 7500.00,
                'balance_after' => 7500.00,
            ]
        );

        if ($generatedAdminPassword) {
            $this->command?->warn("[SEED LOCAL] Senha gerada para admin@digitalcard.com (exibida uma única vez): {$adminPlainPassword}");
        }

        if ($generatedClientPassword) {
            $this->command?->warn("[SEED LOCAL] Senha gerada para cliente@digitalcard.com (exibida uma única vez): {$clientPlainPassword}");
        }

        // 5. Cupons promocionais adicionais e segundo cliente de demonstração (para testes de transferência P2P)
        Coupon::updateOrCreate(
            ['code' => 'SETUP15'],
            [
                'label' => '15% OFF na linha Escritório & Setup acima de R$ 1.200',
                'type' => Coupon::TYPE_PERCENT,
                'value' => 15.00,
                'min_total' => 1200.00,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(6),
                'max_uses' => 250,
                'per_user_limit' => 2,
                'is_active' => true,
            ]
        );

        Coupon::updateOrCreate(
            ['code' => 'TECH100'],
            [
                'label' => 'R$ 100 OFF em compras acima de R$ 899',
                'type' => Coupon::TYPE_FIXED,
                'value' => 100.00,
                'min_total' => 899.00,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(6),
                'max_uses' => 300,
                'per_user_limit' => 2,
                'is_active' => true,
            ]
        );

        $marina = User::updateOrCreate(
            ['email' => 'marina@digitalcard.com'],
            [
                'name' => 'Marina Souza',
                'password' => Hash::make($clientPlainPassword),
            ]
        );
        $marina->forceFill(['is_admin' => false, 'email_verified_at' => now()])->save();

        $marinaCard = $marina->virtualCard()->firstOrCreate([], [
            'card_number' => VirtualCard::generateUniqueCardNumber(),
            'cvv' => VirtualCard::generateCvv(),
            'expiration_date' => VirtualCard::DEFAULT_EXPIRATION_DATE,
            'balance' => 0.00,
            'cashback_earned' => 0.00,
        ]);

        $marinaCard->update([
            'balance' => 1800.00,
            'cashback_earned' => 45.00,
            'security_pin' => Hash::make('1234'),
        ]);

        CardTransaction::firstOrCreate(
            [
                'virtual_card_id' => $marinaCard->id,
                'description' => 'Saldo inicial da carteira (Marina)',
            ],
            [
                'type' => CardTransaction::TYPE_RECHARGE,
                'amount' => 1800.00,
                'balance_after' => 1800.00,
            ]
        );

        // Pedido entregue inicial para o cliente iniciar no nível Prata do Clube DigitalCard
        if ($ps5 = Product::where('slug', 'playstation-5-slim-1tb')->first()) {
            $seedOrder = Order::firstOrCreate(
                ['idempotency_key' => 'seed-client-initial-delivered-order'],
                [
                    'user_id' => $client->id,
                    'subtotal_amount' => (float) $ps5->price,
                    'discount_amount' => 0.00,
                    'shipping_cost' => 0.00,
                    'total_amount' => (float) $ps5->price,
                    'cashback_total' => round((float) $ps5->price * ((float) $ps5->cashback_percent / 100), 2),
                    'status' => Order::STATUS_DELIVERED,
                    'shipping_address' => 'Av. Paulista, 1578 - Bela Vista, São Paulo - SP (CEP: 01310-200)',
                    'shipping_cep' => '01310-200',
                    'tracking_code' => 'DCBR990042108BR',
                    'delivered_at' => now()->subDays(5),
                    'cashback_credited_at' => now()->subDays(5),
                ]
            );

            if ($seedOrder->items()->count() === 0) {
                OrderItem::create([
                    'order_id' => $seedOrder->id,
                    'product_id' => $ps5->id,
                    'product_name' => $ps5->name,
                    'product_sku' => $ps5->slug,
                    'quantity' => 1,
                    'unit_price' => (float) $ps5->price,
                    'cashback_percent' => (float) $ps5->cashback_percent,
                    'discount_allocated' => 0.00,
                    'item_status' => 'normal',
                ]);

                OrderStatusHistory::create([
                    'order_id' => $seedOrder->id,
                    'from_status' => null,
                    'to_status' => Order::STATUS_DELIVERED,
                    'changed_by' => $admin->id,
                    'notes' => 'Pedido demonstrativo entregue (Clube DigitalCard Prata).',
                    'created_at' => now()->subDays(5),
                ]);
            }
        }

        // 6. Avaliações iniciais de referência para desenvolvimento
        $sampleReviews = [
            'smartphone-galaxy-s24-ultra' => [$client->id, 5, 'Aparelho espetacular! Comprei com o saldo do Cartão Virtual, entrou na hora como Produto solicitado e quando foi entregue já recebi o cashback direto na carteira.'],
            'playstation-5-slim-1tb' => [$client->id, 5, 'Entrega muito rápida! Console lacrado e o desconto com o cupom DIGITAL10 valeu muito a pena.'],
            'headphone-sony-wh-1000xm5-bluetooth' => [$client->id, 5, 'Ótimo cancelamento de ruído. Pagamento pelo DigitalCard aprovou no mesmo segundo.'],
            'air-fryer-philips-walita-essential-xl' => [$client->id, 5, 'Muito espaçosa e silenciosa, além de dar 6% de cashback de volta no cartão virtual!'],
            'cadeira-ergonomica-herman-miller-aeron' => [$marina->id, 5, 'Qualidade absurda para 8h+ de trabalho por dia. A tela Pellicle não esquenta e o ajuste lombar faz muita diferença.'],
            'cadeira-ergonomica-elements-astra-pro' => [$client->id, 5, 'Excelente custo-benefício em cadeira ergonômica. Apoio de cabeça e braços 3D muito confortáveis.'],
            'mesa-regulavel-eletrica-geniodesks-pro' => [$marina->id, 5, 'Motor duplo super silencioso e tampo em nogueira impecável. Alternar entre sentado e em pé mudou minha rotina.'],
            'microfone-dinamico-shure-sm7db' => [$client->id, 5, 'O pré-amp embutido dispensa Cloudlifter e entrega voz de estúdio limpa para reuniões e podcasts.'],
            'elgato-stream-deck-mk2-15-teclas' => [$marina->id, 5, 'Agilizou todos os meus atalhos no VS Code, Figma e chamadas de vídeo com um toque.'],
            'power-bank-anker-prime-27650mah-250w' => [$client->id, 5, 'Carrega meu notebook e smartphone juntos em viagem sem sufoco. O visor de watts ajuda demais.'],
            'robo-aspirador-roborock-q-revo' => [$marina->id, 5, 'Mapeia a casa inteira com precisão e a base lava e seca os mops sozinha. Zero trabalho no dia a dia.'],
            'cafeteira-delonghi-magnifica-start' => [$client->id, 5, 'Moedor embutido na hora deixa o espresso com crema perfeita toda manhã. Cashback caiu direto na carteira.'],
            'apple-mac-mini-m4-16gb-512gb' => [$marina->id, 5, 'Minúsculo, silencioso e extremamente rápido com o chip M4. Ocupou quase nada na mesa.'],
            'monitor-lg-dualup-28-ergo-qhd' => [$client->id, 5, 'Formato vertical 16:18 substitui dois monitores empilhados com cabo USB-C único carregando o notebook.'],
            'console-portatil-asus-rog-ally-x-1tb' => [$marina->id, 4, 'Bateria de 80Wh aguenta viagens longas e os 24GB de RAM deixam jogos pesados muito fluidos.'],
            'camera-dji-osmo-pocket-3-creator-combo' => [$client->id, 5, 'Estabilização de 3 eixos impecável e o transmissor DJI Mic 2 no combo grava áudio cristalino.'],
        ];

        foreach ($sampleReviews as $slug => [$userId, $rating, $comment]) {
            if ($prod = Product::where('slug', $slug)->first()) {
                ProductReview::updateOrCreate(
                    ['product_id' => $prod->id, 'user_id' => $userId],
                    ['rating' => $rating, 'comment' => $comment]
                );
            }
        }

        // 7. Perguntas e respostas técnicas iniciais nos produtos
        $sampleQuestions = [
            'cadeira-ergonomica-herman-miller-aeron' => [
                $client->id,
                'O tamanho B atende bem pessoas entre 1,68m e 1,85m? Vem com ajuste PostureFit SL?',
                'Sim! O tamanho B é o padrão corporativo ideal entre 1,60m e 1,90m (até 159kg) e esta versão já inclui suporte lombar duplo PostureFit SL.',
            ],
            'apple-mac-mini-m4-16gb-512gb' => [
                $marina->id,
                'Consigo ligar dois monitores 4K simultâneos sem adaptador externo especial?',
                'Sim! O Mac mini M4 suporta até três monitores ao mesmo tempo usando a porta HDMI 2.1 integrada e as portas Thunderbolt 4 USB-C traseiras.',
            ],
            'microfone-dinamico-shure-sm7db' => [
                $client->id,
                'Preciso comprar Cloudlifter ou pré-amp separado para usar com a Scarlett 2i2?',
                'Não precisa! O modelo SM7dB já integra um pré-amplificador ativo desenhado em parceria com a Cloud de +18dB ou +28dB com chave bypass.',
            ],
            'robo-aspirador-roborock-q-revo' => [
                $marina->id,
                'A base autolimpante exige sacos específicos ou funciona direto com reservatório de água limpa e suja?',
                'A estação lava e seca os mops automaticamente usando tanques separados de água limpa e suja e coleta poeira em saco descartável de grande capacidade (até 7 semanas).',
            ],
            'playstation-5-slim-1tb' => [
                $marina->id,
                'Este modelo é a versão com leitor de discos Ultra HD Blu-ray e garantia nacional?',
                'Sim, é a edição oficial brasileira com leitor acoplado modular, SSD de 1TB e 1 controle sem fio DualSense na caixa.',
            ],
            'anel-inteligente-samsung-galaxy-ring' => [
                $client->id,
                'A bateria aguenta quantos dias reais fora do estojo de carregamento?',
                'Entre 6 e 7 dias contínuos dependendo do tamanho do aro, com recarga rápida diretamente no estojo translúcido portátil.',
            ],
        ];

        foreach ($sampleQuestions as $slug => [$askerId, $qText, $aText]) {
            if ($prod = Product::where('slug', $slug)->first()) {
                ProductQuestion::updateOrCreate(
                    ['product_id' => $prod->id, 'user_id' => $askerId, 'question' => $qText],
                    [
                        'answer' => $aText,
                        'answered_by' => $admin->id,
                        'answered_at' => now()->subDays(2),
                        'is_public' => true,
                    ]
                );
            }
        }

        // 8. Meta inicial de Cofrinho DigitalCard (valor reservado 0.00 para preservar reconciliação inicial da carteira)
        if ($macMini = Product::where('slug', 'apple-mac-mini-m4-16gb-512gb')->first()) {
            SavingsGoal::firstOrCreate(
                ['user_id' => $client->id, 'name' => 'Setup Apple Mac mini M4'],
                [
                    'product_id' => $macMini->id,
                    'target_amount' => (float) $macMini->price,
                    'current_amount' => 0.00,
                ]
            );
        }
    }
}
