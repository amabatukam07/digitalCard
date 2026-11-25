<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminPaymentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless((bool) $request->user()?->hasAdminAccess(), 403);

        $currentTab = (string) $request->input('tab', Payment::STATUS_PENDING);
        $search = trim((string) $request->input('q', ''));
        $validStatuses = [
            Payment::STATUS_PENDING,
            Payment::STATUS_APPROVED,
            Payment::STATUS_REJECTED,
            Payment::STATUS_EXPIRED,
        ];

        $query = Payment::with(['company', 'contract.plan', 'requestedBy', 'reviewer'])->latest();

        if (in_array($currentTab, $validStatuses, true)) {
            $query->where('status', $currentTab);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('txid', 'like', $like)
                    ->orWhereHas('company', function ($companyQuery) use ($like): void {
                        $companyQuery->where('trade_name', 'like', $like)
                            ->orWhere('legal_name', 'like', $like)
                            ->orWhere('cnpj', 'like', $like);
                    })
                    ->orWhereHas('requestedBy', function ($userQuery) use ($like): void {
                        $userQuery->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
            });
        }

        $payments = $query->paginate(15)->withQueryString();

        $aggregated = Payment::query()
            ->select(
                'status',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'aprovado' THEN COALESCE(approved_amount, amount) ELSE amount END) as total_amount")
            )
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $counts = [
            'pendente' => (int) ($aggregated->get(Payment::STATUS_PENDING)?->total ?? 0),
            'aprovado' => (int) ($aggregated->get(Payment::STATUS_APPROVED)?->total ?? 0),
            'recusado' => (int) ($aggregated->get(Payment::STATUS_REJECTED)?->total ?? 0),
            'expirado' => (int) ($aggregated->get(Payment::STATUS_EXPIRED)?->total ?? 0),
            'todos' => (int) $aggregated->sum('total'),
        ];

        $pendingTotal = round((float) ($aggregated->get(Payment::STATUS_PENDING)?->total_amount ?? 0.0), 2);
        $approvedTotal = round((float) ($aggregated->get(Payment::STATUS_APPROVED)?->total_amount ?? 0.0), 2);

        return view('admin.payments.index', compact(
            'payments',
            'currentTab',
            'search',
            'counts',
            'pendingTotal',
            'approvedTotal',
        ));
    }
}
