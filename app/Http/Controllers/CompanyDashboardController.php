<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CompanyUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompanyDashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $company = $user->primaryCompany();

        if (! $company) {
            if ($user->hasAdminAccess()) {
                return redirect()
                    ->route('admin.dashboard')
                    ->with('warning', 'Contas administrativas do Backoffice não possuem empresa cliente vinculada. Você foi redirecionado ao painel administrativo.');
            }

            abort(403, 'Sua conta não está vinculada a nenhuma empresa cliente.');
        }

        Gate::authorize('view', $company);

        $wallet = $company->wallet()->first();
        $activeContract = $company->activeContract();

        $validOrdersQuery = Order::query()
            ->where('company_id', $company->id)
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_RETURNED]);

        $spentThisMonth = (float) (clone $validOrdersQuery)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total_amount');

        $inProgressOrdersCount = Order::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [
                Order::STATUS_AWAITING_APPROVAL,
                Order::STATUS_REQUESTED,
                Order::STATUS_SEPARATING,
                Order::STATUS_SHIPPED,
            ])
            ->count();

        $awaitingApprovalOrders = Order::query()
            ->with(['user', 'items'])
            ->where('company_id', $company->id)
            ->where('status', Order::STATUS_AWAITING_APPROVAL)
            ->latest()
            ->limit(10)
            ->get();

        $recentOrders = Order::query()
            ->with(['user', 'items'])
            ->where('company_id', $company->id)
            ->latest()
            ->limit(8)
            ->get();

        // Consumo mensal últimos 6 meses (agregação em PHP para total portabilidade MySQL/SQLite)
        $sixMonthsStart = now()->subMonths(5)->startOfMonth();
        $recentValidOrders = (clone $validOrdersQuery)
            ->where('created_at', '>=', $sixMonthsStart)
            ->get(['id', 'total_amount', 'created_at']);

        $monthlyTotals = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthKey = now()->subMonths($i)->format('Y-m');
            $monthlyTotals[$monthKey] = [
                'label' => ucfirst(Carbon::createFromFormat('Y-m', $monthKey)->locale('pt_BR')->translatedFormat('M/y')),
                'total' => 0.0,
            ];
        }
        foreach ($recentValidOrders as $ord) {
            $mKey = $ord->created_at?->format('Y-m');
            if ($mKey && isset($monthlyTotals[$mKey])) {
                $monthlyTotals[$mKey]['total'] = round($monthlyTotals[$mKey]['total'] + (float) $ord->total_amount, 2);
            }
        }
        $maxMonthly = max(1.0, (float) collect($monthlyTotals)->max('total'));
        $monthlyConsumption = collect($monthlyTotals)->map(fn (array $row): array => [
            'label' => $row['label'],
            'total' => $row['total'],
            'percent' => $row['total'] > 0 ? (int) min(100, max(8, round(($row['total'] / $maxMonthly) * 100))) : 0,
        ])->values();

        // Consumo por categoria
        $categoryConsumptionRaw = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.company_id', $company->id)
            ->whereNotIn('orders.status', [Order::STATUS_CANCELLED, Order::STATUS_RETURNED])
            ->selectRaw('categories.name as category_name, SUM(order_items.quantity * order_items.unit_price) as total_spent, SUM(order_items.quantity) as total_units')
            ->groupBy('categories.name')
            ->orderByDesc('total_spent')
            ->get();

        $maxCatSpent = max(1.0, (float) ($categoryConsumptionRaw->max('total_spent') ?? 1.0));
        $categoryConsumption = $categoryConsumptionRaw->map(fn ($row): array => [
            'name' => (string) $row->category_name,
            'total' => (float) $row->total_spent,
            'units' => (int) $row->total_units,
            'percent' => (int) min(100, max(8, round(((float) $row->total_spent / $maxCatSpent) * 100))),
        ]);

        // Top produtos comprados pela empresa
        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.company_id', $company->id)
            ->whereNotIn('orders.status', [Order::STATUS_CANCELLED, Order::STATUS_RETURNED])
            ->selectRaw('order_items.product_id, MAX(order_items.product_name) as product_name, SUM(order_items.quantity) as total_units, SUM(order_items.quantity * order_items.unit_price) as total_spent')
            ->groupBy('order_items.product_id')
            ->orderByDesc('total_units')
            ->limit(5)
            ->get();

        $members = $company->memberships()->with('user')->orderBy('id')->get();
        $isManager = $user->isCompanyManager($company);

        $availableCredits = (float) ($wallet->balance ?? 0.0);
        $totalAcquired = (float) ($wallet->total_acquired ?? 0.0);
        $totalSpent = (float) ($wallet->total_spent ?? 0.0);
        $budgetUsedPercent = $totalAcquired > 0
            ? (int) min(100, max(0, round(($totalSpent / $totalAcquired) * 100)))
            : 0;
        $isLowBalance = $availableCredits <= (float) $company->low_balance_threshold;

        return view('company.dashboard', compact(
            'company',
            'wallet',
            'activeContract',
            'availableCredits',
            'totalAcquired',
            'totalSpent',
            'budgetUsedPercent',
            'isLowBalance',
            'spentThisMonth',
            'inProgressOrdersCount',
            'awaitingApprovalOrders',
            'recentOrders',
            'monthlyConsumption',
            'categoryConsumption',
            'topProducts',
            'members',
            'isManager',
        ));
    }

    public function storeMember(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $company = $actor->primaryCompany();
        abort_unless($company !== null, 403);
        Gate::authorize('manageTeam', $company);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'company_role' => ['required', 'in:manager,requester'],
            'spending_limit' => ['nullable', 'numeric', 'min:0'],
            'department' => ['nullable', 'string', 'max:80'],
        ]);

        DB::transaction(function () use ($validated, $company, $actor): void {
            $newUser = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $newUser->forceFill([
                'is_admin' => false,
                'role' => $validated['company_role'] === CompanyUser::ROLE_MANAGER
                    ? UserRole::CompanyManager->value
                    : UserRole::CompanyRequester->value,
                'email_verified_at' => now(),
            ])->save();

            $membership = CompanyUser::create([
                'company_id' => $company->id,
                'user_id' => $newUser->id,
                'company_role' => $validated['company_role'],
                'spending_limit' => $validated['spending_limit'] !== null && $validated['spending_limit'] !== ''
                    ? round((float) $validated['spending_limit'], 2)
                    : null,
                'department' => $validated['department'] ?? null,
                'is_active' => true,
            ]);

            AuditLog::record(
                'company.member_created',
                $membership,
                null,
                ['user_id' => $newUser->id, 'company_role' => $membership->company_role],
                $actor->id,
                $company->id
            );
        });

        return back()->with('success', "Usuário {$validated['name']} adicionado à equipe da empresa.");
    }

    public function updateMember(Request $request, CompanyUser $companyUser): RedirectResponse
    {
        $actor = $request->user();
        $company = $actor->primaryCompany();
        abort_unless($company !== null && (int) $companyUser->company_id === (int) $company->id, 403);
        Gate::authorize('manageTeam', $company);

        $validated = $request->validate([
            'company_role' => ['required', 'in:manager,requester'],
            'spending_limit' => ['nullable', 'numeric', 'min:0'],
            'department' => ['nullable', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ((int) $companyUser->user_id === (int) $actor->id && $validated['company_role'] !== CompanyUser::ROLE_MANAGER) {
            throw ValidationException::withMessages([
                'company_role' => 'Você não pode rebaixar o próprio perfil de Gestor.',
            ]);
        }

        $oldValues = [
            'company_role' => $companyUser->company_role,
            'spending_limit' => $companyUser->spending_limit,
            'is_active' => (bool) $companyUser->is_active,
        ];

        $isActive = $request->has('is_active') ? $request->boolean('is_active') : $companyUser->is_active;

        $companyUser->update([
            'company_role' => $validated['company_role'],
            'spending_limit' => $validated['spending_limit'] !== null && $validated['spending_limit'] !== ''
                ? round((float) $validated['spending_limit'], 2)
                : null,
            'department' => $validated['department'] ?? $companyUser->department,
            'is_active' => (bool) $isActive,
        ]);

        $companyUser->user?->forceFill([
            'role' => $validated['company_role'] === CompanyUser::ROLE_MANAGER
                ? UserRole::CompanyManager->value
                : UserRole::CompanyRequester->value,
        ])->save();

        AuditLog::record(
            'company.member_updated',
            $companyUser,
            $oldValues,
            [
                'company_role' => $companyUser->company_role,
                'spending_limit' => $companyUser->spending_limit,
                'is_active' => (bool) $companyUser->is_active,
            ],
            $actor->id,
            $company->id
        );

        return back()->with('success', 'Permissões do colaborador atualizadas com sucesso.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $company = $actor->primaryCompany();
        abort_unless($company !== null, 403);
        Gate::authorize('manageTeam', $company);

        $validated = $request->validate([
            'require_order_approval' => ['nullable', 'boolean'],
            'low_balance_threshold' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_cep' => ['nullable', 'string', 'max:10'],
        ]);

        $old = [
            'require_order_approval' => (bool) $company->require_order_approval,
            'low_balance_threshold' => (float) $company->low_balance_threshold,
        ];

        $company->update([
            'require_order_approval' => $request->boolean('require_order_approval'),
            'low_balance_threshold' => round((float) $validated['low_balance_threshold'], 2),
            'billing_address' => $validated['billing_address'] ?? $company->billing_address,
            'billing_cep' => $validated['billing_cep'] ?? $company->billing_cep,
        ]);

        AuditLog::record(
            'company.settings_updated',
            $company,
            $old,
            [
                'require_order_approval' => (bool) $company->require_order_approval,
                'low_balance_threshold' => (float) $company->low_balance_threshold,
            ],
            $actor->id,
            $company->id
        );

        return back()->with('success', 'Configurações de governança da empresa atualizadas.');
    }
}
