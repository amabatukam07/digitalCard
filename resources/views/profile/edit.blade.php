<x-app-layout :metaTitle="'Minha Conta e Endereços'" :metaDescription="'Gerencie seus dados cadastrais, endereços corporativos e senha na NexoSupri.'">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-serif text-2xl text-ink leading-tight">
                Minha Conta
            </h2>
            @if(isset($company) && $company)
                <a href="{{ route('company.dashboard') }}" class="text-xs font-medium text-ink underline hover:text-accent-600">
                    Empresa: {{ $company->trade_name }} ({{ $company->cnpj }}) →
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-10">
        <div class="app-shell space-y-6">
            <div class="p-4 sm:p-8 bg-surface border border-line rounded-xl shadow-sm">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-surface border border-line rounded-xl shadow-sm space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-medium text-ink flex items-center gap-2">
                            <x-icon name="bell-ring" class="size-5 text-accent-600" />
                            <span>Alertas de Preço de Insumos</span>
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">Monitore automaticamente insumos até atingirem o valor-alvo desejado.</p>
                    </div>
                </div>

                @if(($priceAlerts ?? collect())->isNotEmpty())
                    <div class="divide-y divide-line rounded-lg border border-line bg-paper overflow-hidden">
                        @foreach($priceAlerts as $alert)
                            @php
                                $p = $alert->product;
                                $reached = $p && (float) $p->price <= (float) $alert->target_price;
                            @endphp
                            @if($p)
                                <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <a href="{{ route('catalog.product', $p->slug) }}" class="w-12 shrink-0 rounded border border-line bg-white overflow-hidden">
                                            <x-product-image :product="$p" size="thumb" />
                                        </a>
                                        <div class="min-w-0">
                                            <a href="{{ route('catalog.product', $p->slug) }}" class="font-semibold text-ink hover:text-accent-600 truncate block">
                                                {{ $p->name }}
                                            </a>
                                            <div class="flex flex-wrap items-center gap-2 text-slate-600 mt-0.5 tabular-nums">
                                                <span>Atual: <strong class="font-mono text-ink">R$ {{ number_format((float) $p->price, 2, ',', '.') }}</strong></span>
                                                <span>·</span>
                                                <span>Sua meta: <strong class="font-mono text-money-700">R$ {{ number_format((float) $alert->target_price, 2, ',', '.') }}</strong></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2 py-0.5 rounded border text-[11px] font-semibold {{ $reached ? 'border-money-200 bg-money-50 text-money-800' : 'border-accent-200 bg-accent-50 text-accent-900' }}">
                                            {{ $reached ? 'Meta atingida!' : 'Monitorando' }}
                                        </span>
                                        <form action="{{ route('price-alerts.destroy', $alert) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-md border border-danger-200 bg-surface px-3 py-1.5 font-medium text-danger-700 hover:bg-danger-50 transition">
                                                Remover
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @else
                    <div class="rounded-lg border border-line bg-paper p-5 text-xs text-slate-600 flex flex-wrap items-center justify-between gap-3">
                        <span>Você ainda não criou alertas de queda de preço em nenhum insumo.</span>
                        <a href="{{ route('catalog.index') }}" class="font-semibold text-ink hover:underline">Explorar catálogo →</a>
                    </div>
                @endif
            </div>

            <div class="p-4 sm:p-8 bg-surface border border-line rounded-xl shadow-sm space-y-6">
                <div class="max-w-3xl space-y-4">
                    <header>
                        <h2 class="text-lg font-medium text-ink">Endereços Corporativos de Entrega</h2>
                        <p class="mt-1 text-sm text-slate-600">Gerencie as unidades e almoxarifados cadastrados para recebimento de pedidos.</p>
                    </header>

                    @if(($addresses ?? collect())->isNotEmpty())
                        <div class="divide-y divide-line rounded-lg border border-line bg-paper">
                            @foreach($addresses as $address)
                                <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-ink">{{ $address->label }}</span>
                                            @if($address->is_default)
                                                <span class="rounded bg-money-50 border border-money-200 px-2 py-0.5 text-money-800 font-medium">Padrão</span>
                                            @endif
                                        </div>
                                        <p class="text-slate-700">{{ $address->formattedLine() }}</p>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        @unless($address->is_default)
                                            <form action="{{ route('addresses.default', $address) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-md border border-line bg-surface px-3 py-1.5 font-medium text-ink hover:border-ink transition">
                                                    Definir padrão
                                                </button>
                                            </form>
                                        @endunless

                                        <form action="{{ route('addresses.destroy', $address) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-md border border-danger-200 bg-surface px-3 py-1.5 font-medium text-danger-700 hover:bg-danger-50 transition">
                                                Excluir
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <form action="{{ route('addresses.store') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 text-xs">
                        @csrf
                        <div>
                            <label for="addr_label" class="block font-medium text-slate-700 mb-1">Identificação da unidade (ex: Matriz, Filial Sul)</label>
                            <input id="addr_label" type="text" name="label" placeholder="Matriz" maxlength="80" class="w-full rounded-md border-line text-xs py-2">
                        </div>
                        <div>
                            <label for="addr_cep" class="block font-medium text-slate-700 mb-1">CEP</label>
                            <input id="addr_cep" type="text" name="cep" required placeholder="00000-000" maxlength="15" class="w-full rounded-md border-line font-mono text-xs py-2">
                        </div>
                        <div>
                            <label for="addr_street" class="block font-medium text-slate-700 mb-1">Rua / Logradouro (opcional, busca pelo CEP)</label>
                            <input id="addr_street" type="text" name="street" maxlength="180" class="w-full rounded-md border-line text-xs py-2">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="addr_number" class="block font-medium text-slate-700 mb-1">Número</label>
                                <input id="addr_number" type="text" name="number" placeholder="1578" maxlength="30" class="w-full rounded-md border-line text-xs py-2">
                            </div>
                            <div>
                                <label for="addr_complement" class="block font-medium text-slate-700 mb-1">Complemento</label>
                                <input id="addr_complement" type="text" name="complement" maxlength="120" class="w-full rounded-md border-line text-xs py-2">
                            </div>
                        </div>
                        <div class="sm:col-span-2 flex items-center justify-between gap-3 pt-1">
                            <label class="inline-flex items-center gap-2 text-slate-700">
                                <input type="checkbox" name="is_default" value="1" checked class="rounded border-line">
                                <span>Definir como endereço padrão</span>
                            </label>
                            <button type="submit" class="rounded-md bg-ink hover:bg-slate-800 px-4 py-2 font-semibold text-paper transition">
                                Salvar endereço
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-surface border border-line rounded-xl shadow-sm">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-surface border border-line rounded-xl shadow-sm">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
