<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Models\Address;
use App\Models\Category;
use App\Models\Company;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Policies\AddressPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Services\CartPricing;
use App\Services\Payment\SimulatedPixGateway;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, SimulatedPixGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale', 'pt_BR'));

        Gate::define('admin', fn (User $user): bool => $user->hasAdminAccess());
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Address::class, AddressPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);

        $this->configureRateLimiters();

        Password::defaults(function () {
            $rule = Password::min(10)->letters()->numbers();

            return $this->app->isProduction()
                ? $rule->mixedCase()->symbols()->uncompromised()
                : Password::min(8);
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            try {
                DB::statement('PRAGMA foreign_keys = ON;');
                DB::statement('PRAGMA journal_mode = WAL;');
                DB::statement('PRAGMA busy_timeout = 5000;');
            } catch (\Throwable) {
                // Ignora quando o arquivo SQLite ainda não foi inicializado
            }
        }

        View::composer(['layouts.app', 'layouts.navigation', 'catalog.*'], function ($view): void {
            $view->with('navCategories', $this->resolveNavCategories());
            $compareIds = array_values(array_unique(array_filter(
                array_map('intval', session('compare_product_ids', [])),
                fn (int $id): bool => $id > 0
            )));
            $view->with('compareIds', $compareIds);
            $view->with('compareCount', count($compareIds));

            $authId = auth()->id();
            $currentCompany = null;
            $currentWallet = null;
            $currentPlanName = null;

            if ($authId !== null) {
                $reqAttrs = request()->attributes;
                $cachedContext = $reqAttrs->get('_nexosupri_view_context');

                if (is_array($cachedContext) && ($cachedContext['user_id'] ?? null) === $authId) {
                    $currentCompany = $cachedContext['company'];
                    $currentWallet = $cachedContext['wallet'];
                    $currentPlanName = $cachedContext['planName'];
                } else {
                    try {
                        $currentCompany = auth()->user()?->primaryCompany();
                        $currentWallet = $currentCompany?->wallet;
                        $currentPlanName = $currentCompany?->activeContract()?->plan?->name;
                    } catch (\Throwable) {
                        $currentCompany = null;
                        $currentWallet = null;
                        $currentPlanName = null;
                    }

                    $reqAttrs->set('_nexosupri_view_context', [
                        'user_id' => $authId,
                        'company' => $currentCompany,
                        'wallet' => $currentWallet,
                        'planName' => $currentPlanName,
                    ]);
                }
            }

            $view->with('currentCompany', $currentCompany);
            $view->with('currentWallet', $currentWallet);
            $view->with('currentPlanName', $currentPlanName);
        });

        View::composer(['layouts.navigation', 'layouts.admin'], function ($view): void {
            $counts = $this->resolveAdminPendingCounts();

            $view->with([
                'adminPendingPaymentsCount' => $counts['payments'],
                'adminPendingOrdersCount' => $counts['orders'],
            ]);
        });

        View::composer('layouts.navigation', function ($view): void {
            $rawCart = session('cart', []);
            $cartItemCount = collect($rawCart)->sum('quantity');
            $wishlistCount = count(session('wishlist', []));

            $drawerItems = [];
            $drawerTotal = 0.0;

            if (! empty($rawCart)) {
                try {
                    $summary = app(CartPricing::class)->calculate($rawCart);
                    $drawerTotal = $summary['subtotalAll'];
                    $drawerItems = array_map(static fn (array $line): array => [
                        'product' => $line['product'],
                        'quantity' => $line['quantity'],
                        'subtotal' => $line['subtotal'],
                    ], $summary['items']);
                } catch (QueryException) {
                    $drawerItems = [];
                }
            }

            $unreadNotificationsCount = 0;
            $recentNotifications = collect();
            $pendingCompanyApprovalsCount = 0;

            if (auth()->check()) {
                try {
                    $authUser = auth()->user();
                    $unreadNotificationsCount = (int) $authUser->unreadNotifications()->count();
                    $recentNotifications = $authUser->notifications()->latest()->limit(5)->get();

                    $company = $authUser->primaryCompany();
                    if ($company && $authUser->isCompanyManager($company)) {
                        $pendingCompanyApprovalsCount = Order::where('company_id', $company->id)
                            ->where('status', Order::STATUS_AWAITING_APPROVAL)
                            ->count();
                    }
                } catch (\Throwable) {
                    $unreadNotificationsCount = 0;
                    $recentNotifications = collect();
                    $pendingCompanyApprovalsCount = 0;
                }
            }

            $view->with(compact(
                'cartItemCount',
                'wishlistCount',
                'drawerItems',
                'drawerTotal',
                'unreadNotificationsCount',
                'recentNotifications',
                'pendingCompanyApprovalsCount',
            ));
        });
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('coupon-apply', function (Request $request): Limit {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('pix-generate', function (Request $request): Limit {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('receipt-upload', function (Request $request): Limit {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('cep-lookup', function (Request $request): Limit {
            return Limit::perMinute(30)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('checkout', function (Request $request): Limit {
            return Limit::perMinute(20)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('search-suggest', function (Request $request): Limit {
            return Limit::perMinute(90)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('product-question', function (Request $request): Limit {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip()));
        });
    }

    /**
     * @return list<array{id: int, name: string, slug: string, icon: string|null, children: list<array{id: int, name: string, slug: string, icon: string|null}>}>
     */
    private function resolveNavCategories(): array
    {
        try {
            $cachedTree = Cache::remember('nav_categories_tree', 3600, function (): array {
                return Category::with('children')
                    ->whereNull('parent_id')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (Category $cat): array => [
                        'id' => (int) $cat->id,
                        'name' => (string) $cat->name,
                        'slug' => (string) $cat->slug,
                        'icon' => $cat->icon,
                        'children' => $cat->children
                            ->map(fn (Category $child): array => [
                                'id' => (int) $child->id,
                                'name' => (string) $child->name,
                                'slug' => (string) $child->slug,
                                'icon' => $child->icon,
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all();
            });

            return is_array($cachedTree) ? $cachedTree : [];
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @return array{payments: int, orders: int}
     */
    private function resolveAdminPendingCounts(): array
    {
        if (! auth()->check() || ! auth()->user()->hasAdminAccess()) {
            return ['payments' => 0, 'orders' => 0];
        }

        try {
            $cached = Cache::remember('admin_pending_counts', 60, function (): array {
                return [
                    'payments' => (int) Payment::where('status', Payment::STATUS_PENDING)->count(),
                    'orders' => (int) Order::whereIn('status', [
                        Order::STATUS_REQUESTED,
                        Order::STATUS_SEPARATING,
                        Order::STATUS_SHIPPED,
                    ])->count(),
                ];
            });

            return [
                'payments' => (int) ($cached['payments'] ?? 0),
                'orders' => (int) ($cached['orders'] ?? 0),
            ];
        } catch (QueryException) {
            return ['payments' => 0, 'orders' => 0];
        }
    }
}
