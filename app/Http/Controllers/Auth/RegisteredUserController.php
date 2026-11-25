<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['nullable', 'string', 'max:30'],
            'company_trade_name' => ['nullable', 'string', 'max:180'],
            'company_legal_name' => ['nullable', 'string', 'max:180'],
            'cnpj' => ['nullable', 'string', 'max:18', 'unique:companies,cnpj'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
            ]);

            $user->forceFill([
                'is_admin' => false,
                'role' => UserRole::CompanyManager->value,
            ])->save();

            $tradeName = trim((string) ($validated['company_trade_name'] ?? '')) ?: ('Empresa de '.$validated['name']);
            $legalName = trim((string) ($validated['company_legal_name'] ?? '')) ?: ($tradeName.' Ltda');
            $cnpj = trim((string) ($validated['cnpj'] ?? '')) ?: $this->generatePlaceholderCnpj();

            $company = Company::create([
                'cnpj' => $cnpj,
                'legal_name' => $legalName,
                'trade_name' => $tradeName,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'status' => Company::STATUS_ACTIVE,
                'require_order_approval' => true,
                'low_balance_threshold' => (float) config('nexosupri.low_balance_alert_threshold', 200.00),
            ]);

            CompanyUser::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'company_role' => CompanyUser::ROLE_MANAGER,
                'department' => 'Administração',
                'is_active' => true,
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('company.dashboard', absolute: false));
    }

    private function generatePlaceholderCnpj(): string
    {
        do {
            $cnpj = sprintf(
                '%02d.%03d.%03d/0001-%02d',
                random_int(10, 99),
                random_int(100, 999),
                random_int(100, 999),
                random_int(10, 99)
            );
        } while (Company::withTrashed()->where('cnpj', $cnpj)->exists());

        return $cnpj;
    }
}
