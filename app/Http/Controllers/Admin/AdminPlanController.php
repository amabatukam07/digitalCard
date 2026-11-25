<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPlanController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless((bool) $request->user()?->hasAdminAccess(), 403);

        $plans = Plan::withCount('contracts')->orderBy('sort_order')->get();
        $contracts = Contract::with(['company', 'plan', 'acceptedBy'])->latest()->paginate(15);

        return view('admin.plans.index', compact('plans', 'contracts'));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCompanies(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'price' => ['required', 'numeric', 'min:100.00'],
            'credits_amount' => ['required', 'numeric', 'min:100.00'],
            'target_profile' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $old = [
            'price' => (float) $plan->price,
            'credits_amount' => (float) $plan->credits_amount,
            'is_active' => (bool) $plan->is_active,
        ];

        $plan->update([
            'name' => $validated['name'],
            'price' => (float) $validated['price'],
            'credits_amount' => (float) $validated['credits_amount'],
            'target_profile' => $validated['target_profile'],
            'description' => $validated['description'] ?? $plan->description,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $plan->is_active,
        ]);

        AuditLog::record(
            'plan.updated',
            $plan,
            $old,
            [
                'price' => (float) $plan->price,
                'credits_amount' => (float) $plan->credits_amount,
                'is_active' => (bool) $plan->is_active,
            ],
            $request->user()->id
        );

        return back()->with('success', "Plano \"{$plan->name}\" atualizado com sucesso.");
    }
}
