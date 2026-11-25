@php
    $currency = static fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $canManageCompanies = (bool) auth()->user()?->canManageCompanies();
@endphp

<x-admin-layout title="Planos & Contratos">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end justify-between border-b border-line pb-5">
            <div>
                <x-eyebrow>Comercial · Modelo A (1:1 sem bônus)</x-eyebrow>
                <h1 class="mt-1 font-serif text-2xl sm:text-3xl text-ink">Planos Comerciais &amp; Contratos</h1>
                <p class="mt-1 text-xs sm:text-sm text-slate-600 max-w-2xl">
                    Ajuste valores e créditos dos 5 planos corporativos conforme a política comercial (paridade recomendada de 1 crédito = R$ 1,00) e acompanhe adesões.
                </p>
            </div>
            <a href="{{ route('admin.dashboard') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface hover:border-ink px-4 py-2 text-xs font-semibold text-ink transition shrink-0">
                <x-icon name="arrow-left" class="size-3.5" />
                <span>Voltar ao Painel</span>
            </a>
        </div>

        {{-- Commercial Rule Callout --}}
        <div class="rounded-xl border border-line bg-surface px-4 py-3 flex items-center justify-between gap-3 text-xs text-slate-700">
            <div class="flex items-center gap-2.5">
                <x-icon name="shield-check" class="size-4 text-money-600 shrink-0" />
                <span>
                    <strong class="text-ink">Regra do Modelo A:</strong> os planos não devem conceder bônus nem deságio antes de validar margem, logística e tributação DAS.
                </span>
            </div>
            @if (! $canManageCompanies)
                <span class="rounded-md bg-paper border border-line px-2.5 py-1 text-[11px] font-semibold text-slate-600 shrink-0">
                    Somente leitura
                </span>
            @endif
        </div>

        {{-- 5 Plans Grid --}}
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($plans as $plan)
                <form method="POST"
                      action="{{ route('admin.plans.update', $plan) }}"
                      x-data="{ price: {{ (float) $plan->price }}, credits: {{ (float) $plan->credits_amount }} }"
                      class="flex flex-col justify-between rounded-xl border border-line bg-surface p-5 shadow-sm">
                    @csrf
                    @method('PATCH')

                    <div class="space-y-3.5 text-xs">
                        <div class="flex items-center justify-between gap-2 pb-2.5 border-b border-line">
                            <span class="rounded-md bg-paper border border-line px-2.5 py-0.5 font-mono text-[11px] font-semibold text-ink tabular-nums">
                                {{ $plan->contracts_count }} {{ $plan->contracts_count === 1 ? 'contrato' : 'contratos' }}
                            </span>
                            <label for="plan_active_{{ $plan->id }}" class="inline-flex items-center gap-2 text-slate-700 cursor-pointer">
                                <input id="plan_active_{{ $plan->id }}" type="checkbox" name="is_active" value="1"
                                       @checked($plan->is_active) @disabled(! $canManageCompanies)
                                       class="rounded border-line text-ink focus:ring-ink">
                                <span class="font-medium">Ativo no portal</span>
                            </label>
                        </div>

                        <div>
                            <label for="plan_name_{{ $plan->id }}" class="block text-[11px] font-semibold text-slate-600 mb-1">Nome do Plano</label>
                            <input id="plan_name_{{ $plan->id }}" type="text" name="name" value="{{ $plan->name }}" required
                                   @readonly(! $canManageCompanies)
                                   class="w-full rounded-lg border-line bg-paper px-3 py-2 font-serif text-base text-ink focus:border-ink focus:ring-ink">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="plan_price_{{ $plan->id }}" class="block text-[11px] font-semibold text-slate-600 mb-1">Valor Pago (R$)</label>
                                <input id="plan_price_{{ $plan->id }}" type="number" name="price" step="0.01" min="100"
                                       x-model.number="price" value="{{ $plan->price }}" required @readonly(! $canManageCompanies)
                                       class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink tabular-nums focus:border-ink focus:ring-ink">
                            </div>
                            <div>
                                <label for="plan_credits_{{ $plan->id }}" class="block text-[11px] font-semibold text-slate-600 mb-1">Créditos Liberados</label>
                                <input id="plan_credits_{{ $plan->id }}" type="number" name="credits_amount" step="0.01" min="100"
                                       x-model.number="credits" value="{{ $plan->credits_amount }}" required @readonly(! $canManageCompanies)
                                       class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink tabular-nums focus:border-ink focus:ring-ink">
                            </div>
                        </div>

                        <div x-show="Math.abs(Number(price) - Number(credits)) > 0.01" x-cloak
                             class="rounded-lg border border-accent-200 bg-accent-50 px-3 py-2 text-[11px] text-accent-700 font-medium">
                            Atenção: valor diferente dos créditos foge da paridade 1:1 do Modelo A.
                        </div>

                        <div>
                            <label for="plan_profile_{{ $plan->id }}" class="block text-[11px] font-semibold text-slate-600 mb-1">Perfil Recomendado</label>
                            <input id="plan_profile_{{ $plan->id }}" type="text" name="target_profile" value="{{ $plan->target_profile }}" required
                                   @readonly(! $canManageCompanies)
                                   class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                        </div>

                        <div>
                            <label for="plan_desc_{{ $plan->id }}" class="block text-[11px] font-semibold text-slate-600 mb-1">Descrição &amp; Benefícios</label>
                            <textarea id="plan_desc_{{ $plan->id }}" name="description" rows="2"
                                      @readonly(! $canManageCompanies)
                                      class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">{{ $plan->description }}</textarea>
                        </div>
                    </div>

                    @if ($canManageCompanies)
                        <button type="submit"
                                class="mt-4 w-full rounded-lg bg-ink hover:bg-slate-800 py-2.5 text-xs font-semibold text-paper transition">
                            Atualizar {{ $plan->name }}
                        </button>
                    @endif
                </form>
            @endforeach
        </div>

        {{-- Recent Contracts --}}
        <div class="overflow-hidden rounded-xl border border-line bg-surface shadow-sm">
            <div class="px-5 py-4 border-b border-line flex items-center justify-between">
                <h2 class="font-serif text-xl text-ink">Contratos &amp; Adesões Recentes</h2>
                <span class="text-xs text-slate-500">Últimas contratações empresariais</span>
            </div>
            <div class="table-scroll-region">
                <table class="min-w-full divide-y divide-line text-xs">
                    <thead class="bg-paper text-left text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">Empresa</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Plano</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Créditos Contratados</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Aceite por</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Contratado em</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($contracts as $contract)
                            <tr class="hover:bg-paper/45 transition-colors">
                                <td class="px-4 py-3 font-semibold text-ink">{{ $contract->company?->trade_name ?? $contract->company?->legal_name }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $contract->plan?->name }}</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-money-700 tabular-nums">{{ $currency($contract->credits_granted) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $contract->acceptedBy?->name ?? 'Sistema' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-md border px-2.5 py-0.5 text-[11px] font-semibold {{ $contract->status === \App\Models\Contract::STATUS_ACTIVE ? 'bg-money-50 border-money-200 text-money-700' : 'bg-paper border-line text-slate-700' }}">
                                        {{ ucfirst($contract->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-slate-500 tabular-nums">{{ $contract->created_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Nenhum contrato registrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $contracts->links() }}</div>
    </div>
</x-admin-layout>
