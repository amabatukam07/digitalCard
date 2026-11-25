<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CreditWallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCompanyController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless((bool) $request->user()?->hasAdminAccess(), 403);

        $search = trim((string) $request->input('q'));
        $statusFilter = trim((string) $request->input('status', ''));

        $companies = Company::query()
            ->with(['wallet', 'contracts.plan'])
            ->withCount(['orders', 'memberships'])
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function ($sub) use ($like): void {
                    $sub->where('trade_name', 'like', $like)
                        ->orWhere('legal_name', 'like', $like)
                        ->orWhere('cnpj', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->when($statusFilter === Company::STATUS_ACTIVE, fn ($query) => $query->where('status', Company::STATUS_ACTIVE))
            ->when($statusFilter === Company::STATUS_SUSPENDED, fn ($query) => $query->where('status', Company::STATUS_SUSPENDED))
            ->when($statusFilter === 'low_balance', fn ($query) => $query->whereHas('wallet', fn ($q) => $q->whereColumn('balance', '<=', 'companies.low_balance_threshold')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $companyStats = [
            'total' => Company::count(),
            'active' => Company::where('status', Company::STATUS_ACTIVE)->count(),
            'suspended' => Company::where('status', Company::STATUS_SUSPENDED)->count(),
            'lowBalance' => Company::whereHas('wallet', fn ($q) => $q->whereColumn('balance', '<=', 'companies.low_balance_threshold'))->count(),
            'openCredits' => (float) CreditWallet::sum('balance'),
        ];

        return view('admin.companies.index', compact('companies', 'search', 'statusFilter', 'companyStats'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCompanies(), 403);

        $validated = $request->validate([
            'cnpj' => ['required', 'string', 'max:18', 'unique:companies,cnpj'],
            'legal_name' => ['required', 'string', 'max:180'],
            'trade_name' => ['required', 'string', 'max:180'],
            'state_registration' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:30'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_cep' => ['nullable', 'string', 'max:10'],
            'require_order_approval' => ['nullable', 'boolean'],
            'low_balance_threshold' => ['nullable', 'numeric', 'min:0'],
        ]);

        $company = Company::create([
            'cnpj' => $validated['cnpj'],
            'legal_name' => $validated['legal_name'],
            'trade_name' => $validated['trade_name'],
            'state_registration' => $validated['state_registration'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'billing_address' => $validated['billing_address'] ?? null,
            'billing_cep' => $validated['billing_cep'] ?? null,
            'status' => Company::STATUS_ACTIVE,
            'require_order_approval' => $request->boolean('require_order_approval', true),
            'low_balance_threshold' => (float) ($validated['low_balance_threshold'] ?? 200.00),
        ]);

        AuditLog::record(
            'company.created',
            $company,
            null,
            ['cnpj' => $company->cnpj, 'trade_name' => $company->trade_name],
            $request->user()->id,
            $company->id
        );

        return back()->with('success', "Empresa \"{$company->trade_name}\" cadastrada com carteira de créditos inicializada.");
    }

    public function updateStatus(Request $request, Company $company): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCompanies(), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:active,suspended'],
        ]);

        $oldStatus = $company->status;
        $company->update(['status' => $validated['status']]);

        AuditLog::record(
            'company.status_updated',
            $company,
            ['status' => $oldStatus],
            ['status' => $company->status],
            $request->user()->id,
            $company->id
        );

        return back()->with('success', "Situação cadastral de {$company->trade_name} atualizada para {$company->status}.");
    }
}
