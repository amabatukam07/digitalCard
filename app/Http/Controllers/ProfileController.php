<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\AuditLog;
use App\Models\CompanyUser;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $company = $user->primaryCompany();
        $addresses = $user->addresses()
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        $priceAlerts = $user->priceAlerts()
            ->with('product.category')
            ->latest()
            ->get();

        return view('profile.edit', [
            'user' => $user,
            'company' => $company,
            'addresses' => $addresses,
            'priceAlerts' => $priceAlerts,
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $company = $user->primaryCompany();

        if ($company) {
            $wallet = $company->wallet;
            $activeManagersCount = CompanyUser::where('company_id', $company->id)
                ->where('company_role', CompanyUser::ROLE_MANAGER)
                ->where('is_active', true)
                ->count();

            if ($wallet && (float) $wallet->balance > 0 && $user->isCompanyManager($company) && $activeManagersCount <= 1) {
                throw ValidationException::withMessages([
                    'balance' => 'Não é possível excluir o único gestor enquanto a carteira da empresa possuir saldo em créditos ('.number_format((float) $wallet->balance, 2, ',', '.').').',
                ])->errorBag('userDeletion');
            }
        }

        $hasOpenOrders = $user->orders()
            ->whereIn('status', [
                Order::STATUS_AWAITING_APPROVAL,
                Order::STATUS_REQUESTED,
                Order::STATUS_SEPARATING,
                Order::STATUS_SHIPPED,
            ])
            ->exists();

        if ($hasOpenOrders) {
            throw ValidationException::withMessages([
                'orders' => 'Não é possível excluir a conta enquanto houver pedidos em andamento.',
            ])->errorBag('userDeletion');
        }

        Auth::logout();

        DB::transaction(function () use ($user, $company): void {
            AuditLog::record(
                'user.account_deleted',
                $user,
                ['email' => $user->email],
                ['anonymized' => true],
                $user->id,
                $company?->id
            );

            $user->companyMemberships()->update(['is_active' => false]);

            $user->forceFill([
                'name' => 'Usuário Removido #'.$user->id,
                'email' => "deleted+{$user->id}@nexosupri.local",
            ])->save();

            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
