<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'essencial',
                'name' => 'Essencial',
                'price' => 500.00,
                'credits_amount' => 500.00,
                'is_custom_amount' => false,
                'min_amount' => 500.00,
                'target_profile' => 'Micro e pequenas empresas',
                'description' => 'Entrada prática no Modelo A para centralizar insumos básicos com conversão exata 1:1 sem bônus.',
                'sort_order' => 1,
                'benefits' => [
                    'Acesso ao catálogo' => 'Sim',
                    'Pedidos pelo portal' => 'Sim',
                    'Consulta de saldo' => 'Sim',
                    'Histórico de compras' => 'Sim',
                    'Atendimento prioritário' => '—',
                    'Relatórios gerenciais' => 'Básico',
                    'Planejamento de abastecimento' => '—',
                    'Entregas programadas' => 'Conforme rota',
                    'Acompanhamento dedicado' => '—',
                ],
            ],
            [
                'slug' => 'business',
                'name' => 'Business',
                'price' => 1000.00,
                'credits_amount' => 1000.00,
                'is_custom_amount' => false,
                'min_amount' => 1000.00,
                'target_profile' => 'Empresas com compras recorrentes',
                'description' => 'Plano de referência para compras recorrentes mensais de escritório, copa, limpeza e higiene.',
                'sort_order' => 2,
                'benefits' => [
                    'Acesso ao catálogo' => 'Sim',
                    'Pedidos pelo portal' => 'Sim',
                    'Consulta de saldo' => 'Sim',
                    'Histórico de compras' => 'Sim',
                    'Atendimento prioritário' => 'Sim',
                    'Relatórios gerenciais' => 'Sim',
                    'Planejamento de abastecimento' => 'Programado',
                    'Entregas programadas' => 'Conforme rota',
                    'Acompanhamento dedicado' => '—',
                ],
            ],
            [
                'slug' => 'corporate',
                'name' => 'Corporate',
                'price' => 2500.00,
                'credits_amount' => 2500.00,
                'is_custom_amount' => false,
                'min_amount' => 2500.00,
                'target_profile' => 'Empresas de médio porte',
                'description' => 'Controle multiusuário com planejamento personalizado para equipes e múltiplos centros de consumo.',
                'sort_order' => 3,
                'benefits' => [
                    'Acesso ao catálogo' => 'Sim',
                    'Pedidos pelo portal' => 'Sim',
                    'Consulta de saldo' => 'Sim',
                    'Histórico de compras' => 'Sim',
                    'Atendimento prioritário' => 'Sim',
                    'Relatórios gerenciais' => 'Sim',
                    'Planejamento de abastecimento' => 'Personalizado',
                    'Entregas programadas' => 'Conforme rota',
                    'Acompanhamento dedicado' => '—',
                ],
            ],
            [
                'slug' => 'premium',
                'name' => 'Premium',
                'price' => 5000.00,
                'credits_amount' => 5000.00,
                'is_custom_amount' => false,
                'min_amount' => 5000.00,
                'target_profile' => 'Empresas com maior volume',
                'description' => 'Relatórios gerenciais detalhados, prioridade logística viável e acompanhamento comercial dedicado.',
                'sort_order' => 4,
                'benefits' => [
                    'Acesso ao catálogo' => 'Sim',
                    'Pedidos pelo portal' => 'Sim',
                    'Consulta de saldo' => 'Sim',
                    'Histórico de compras' => 'Sim',
                    'Atendimento prioritário' => 'Sim',
                    'Relatórios gerenciais' => 'Detalhado',
                    'Planejamento de abastecimento' => 'Personalizado',
                    'Entregas programadas' => 'Prioridade, se viável',
                    'Acompanhamento dedicado' => 'Sim',
                ],
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'price' => 10000.00,
                'credits_amount' => 10000.00,
                'is_custom_amount' => true,
                'min_amount' => 10000.00,
                'target_profile' => 'Grandes operações',
                'description' => 'Contratação sob medida a partir de R$ 10.000,00 (10.000+ créditos) com logística e relatórios personalizados.',
                'sort_order' => 5,
                'benefits' => [
                    'Acesso ao catálogo' => 'Sim',
                    'Pedidos pelo portal' => 'Sim',
                    'Consulta de saldo' => 'Sim',
                    'Histórico de compras' => 'Sim',
                    'Atendimento prioritário' => 'Sim',
                    'Relatórios gerenciais' => 'Personalizado',
                    'Planejamento de abastecimento' => 'Personalizado',
                    'Entregas programadas' => 'Negociado',
                    'Acompanhamento dedicado' => 'Sim',
                ],
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                array_merge($planData, ['is_active' => true])
            );
        }
    }
}
