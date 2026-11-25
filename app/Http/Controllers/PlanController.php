<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();

        $benefitRows = [
            'Acesso ao catálogo',
            'Pedidos pelo portal',
            'Consulta de saldo',
            'Histórico de compras',
            'Atendimento prioritário',
            'Relatórios gerenciais',
            'Planejamento de abastecimento',
            'Entregas programadas',
            'Acompanhamento dedicado',
        ];

        return view('plans.index', compact('plans', 'benefitRows'));
    }
}
