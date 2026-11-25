<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\CartController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function createAdmin(): View
    {
        return view('auth.admin-login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate(false);

        $request->session()->regenerate();

        CartController::mergeSessionIntoDatabase($request);

        return $this->redirectAfterLogin($request, false);
    }

    public function storeAdmin(LoginRequest $request): RedirectResponse
    {
        $request->authenticate(true);

        $request->session()->regenerate();

        CartController::mergeSessionIntoDatabase($request);

        return $this->redirectAfterLogin($request, true);
    }

    private function redirectAfterLogin(Request $request, bool $preferAdmin): RedirectResponse
    {
        $user = $request->user();
        $isAdmin = (bool) $user?->hasAdminAccess();
        $default = $isAdmin
            ? route('admin.dashboard', absolute: false)
            : route('company.dashboard', absolute: false);

        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || $intended === '' || str_starts_with($intended, '//')) {
            return redirect()->to($default);
        }

        $host = parse_url($intended, PHP_URL_HOST);
        if (is_string($host) && $host !== '' && $host !== $request->getHost()) {
            return redirect()->to($default);
        }

        $rawPath = (string) parse_url($intended, PHP_URL_PATH);
        $path = $rawPath === '' ? '/' : '/'.ltrim($rawPath, '/');

        if (in_array($path, ['/login', '/admin/login', '/register'], true)) {
            return redirect()->to($default);
        }

        $isAdminPath = $path === '/admin' || str_starts_with($path, '/admin/');

        if ($isAdmin) {
            if ($preferAdmin && ! $isAdminPath) {
                return redirect()->to($default);
            }

            if (! $preferAdmin && $user?->primaryCompany() === null && $this->isClientCompanyOnlyPath($path)) {
                return redirect()->to($default);
            }

            return redirect()->to($intended);
        }

        if ($isAdminPath) {
            return redirect()->to($default);
        }

        return redirect()->to($intended);
    }

    private function isClientCompanyOnlyPath(string $path): bool
    {
        return $path === '/dashboard'
            || $path === '/minha-empresa'
            || str_starts_with($path, '/minha-empresa/')
            || $path === '/carteira'
            || str_starts_with($path, '/carteira/')
            || $path === '/meu-cartao'
            || $path === '/meus-pedidos'
            || str_starts_with($path, '/meus-pedidos/')
            || $path === '/checkout';
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
