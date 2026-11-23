<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recharge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminRechargeController extends Controller
{
    public function index(Request $request): View
    {
        $currentTab = (string) $request->input('tab', Recharge::STATUS_PENDING);
        $validStatuses = [
            Recharge::STATUS_PENDING,
            Recharge::STATUS_APPROVED,
            Recharge::STATUS_REJECTED,
            Recharge::STATUS_EXPIRED,
        ];

        $query = Recharge::with(['virtualCard.user', 'reviewer'])->latest();

        if (in_array($currentTab, $validStatuses, true)) {
            $query->where('status', $currentTab);
        }

        $recharges = $query->paginate(15)->withQueryString();

        $aggregated = Recharge::query()
            ->select(
                'status',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'aprovado' THEN COALESCE(approved_amount, amount) ELSE amount END) as total_amount")
            )
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $counts = [
            'pendente' => (int) ($aggregated->get(Recharge::STATUS_PENDING)?->total ?? 0),
            'aprovado' => (int) ($aggregated->get(Recharge::STATUS_APPROVED)?->total ?? 0),
            'recusado' => (int) ($aggregated->get(Recharge::STATUS_REJECTED)?->total ?? 0),
            'expirado' => (int) ($aggregated->get(Recharge::STATUS_EXPIRED)?->total ?? 0),
            'todos' => (int) $aggregated->sum('total'),
        ];

        $pendingTotal = round((float) ($aggregated->get(Recharge::STATUS_PENDING)?->total_amount ?? 0.0), 2);
        $approvedTotal = round((float) ($aggregated->get(Recharge::STATUS_APPROVED)?->total_amount ?? 0.0), 2);

        return view('admin.recharges.index', compact(
            'recharges',
            'currentTab',
            'counts',
            'pendingTotal',
            'approvedTotal',
        ));
    }
}
