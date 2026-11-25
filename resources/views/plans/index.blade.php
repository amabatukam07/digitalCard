<x-app-layout :metaTitle="'Planos Comerciais e Modelo A'" :metaDescription="'Conheça os 5 planos da NexoSupri (Essencial, Business, Corporate, Premium e Enterprise) com conversão 1:1 de créditos exclusivos.'">
    <div class="app-shell py-8 space-y-10">
        <div class="bg-surface rounded-2xl border border-line p-6 sm:p-8 space-y-4 shadow-sm">
            <x-eyebrow>Modelo A — Crédito exclusivo da própria empresa</x-eyebrow>
            <h1 class="font-serif text-3xl sm:text-4xl text-ink">Planos Comerciais de Abastecimento</h1>
            <p class="text-sm text-slate-600 max-w-3xl leading-relaxed">
                {{ config('nexosupri.slogan') }} Na NexoSupri, a conversão inicial é exata: <strong>R$ 1,00 = 1 crédito</strong>, sem bônus nem deságio. Os créditos representam poder de compra exclusivo no catálogo NexoSupri — não são dinheiro, não geram saque e não são transferíveis entre empresas.
            </p>
        </div>

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            @foreach($plans as $plan)
                <article class="bg-surface rounded-2xl border {{ $plan->slug === 'business' ? 'border-ink ring-1 ring-ink' : 'border-line' }} {{ $plan->is_custom_amount ? 'sm:col-span-2 lg:col-span-3 xl:col-span-1' : '' }} p-5 flex flex-col justify-between gap-5 shadow-sm">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="font-serif text-2xl text-ink">{{ $plan->name }}</h2>
                            @if($plan->slug === 'business')
                                <span class="rounded bg-ink text-paper px-2 py-0.5 text-[11px] font-semibold">Referência</span>
                            @endif
                        </div>

                        <p class="text-xs font-medium text-accent-700">{{ $plan->target_profile }}</p>

                        <div class="pt-2 border-t border-line">
                            <span class="text-[11px] text-slate-500 block">
                                {{ $plan->is_custom_amount ? 'Investimento a partir de' : 'Investimento único' }}
                            </span>
                            <p class="font-mono text-2xl font-bold text-ink tabular-nums mt-0.5">
                                R$ {{ number_format((float) $plan->price, 2, ',', '.') }}
                            </p>
                            <p class="font-mono text-xs font-semibold text-money-700 tabular-nums mt-1">
                                = {{ $plan->is_custom_amount ? 'A partir de '.number_format((float) $plan->credits_amount, 0, ',', '.') : number_format((float) $plan->credits_amount, 0, ',', '.') }} créditos
                            </p>
                        </div>

                        @if($plan->description)
                            <p class="text-xs text-slate-600 leading-relaxed">{{ $plan->description }}</p>
                        @endif
                    </div>

                    <div class="pt-3 border-t border-line">
                        @auth
                            <a href="{{ route('wallet.show', ['plan' => $plan->slug]) }}"
                               class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-ink hover:bg-slate-800 px-4 py-2.5 text-xs font-semibold text-paper transition">
                                <span>Contratar plano</span>
                                <x-icon name="arrow-right" class="size-3.5" />
                            </a>
                        @else
                            <a href="{{ route('register') }}"
                               class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-line bg-paper hover:border-ink px-4 py-2.5 text-xs font-semibold text-ink transition">
                                <span>Cadastrar empresa</span>
                                <x-icon name="arrow-right" class="size-3.5" />
                            </a>
                        @endauth
                    </div>
                </article>
            @endforeach
        </section>

        <section class="bg-surface rounded-2xl border border-line overflow-hidden shadow-sm">
            <div class="p-6 border-b border-line">
                <x-eyebrow>Comparativo de benefícios</x-eyebrow>
                <h2 class="font-serif text-2xl text-ink mt-1">Matriz de Recursos por Plano</h2>
                <p class="text-xs text-slate-600 mt-1">Os benefícios diferenciam o relacionamento e os relatórios operacionais sem reduzir a conversão 1:1 dos créditos.</p>
            </div>

            <div class="table-scroll-region">
                <table class="w-full min-w-[760px] text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-paper border-b border-line text-slate-600">
                            <th class="py-3.5 px-5 font-semibold">Recurso</th>
                            @foreach($plans as $plan)
                                <th class="py-3.5 px-5 font-semibold text-ink border-l border-line">{{ $plan->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($benefitRows as $benefit)
                            <tr class="hover:bg-paper/40">
                                <th scope="row" class="py-3 px-5 font-medium text-ink bg-paper/40">{{ $benefit }}</th>
                                @foreach($plans as $plan)
                                    @php($val = $plan->benefits[$benefit] ?? '—')
                                    <td class="py-3 px-5 border-l border-line {{ $val === '—' ? 'text-slate-400' : 'font-medium text-ink' }}">
                                        {{ $val }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-line bg-paper p-6 grid grid-cols-1 md:grid-cols-3 gap-5 text-xs">
            <div class="space-y-1">
                <h3 class="font-semibold text-ink flex items-center gap-1.5">
                    <x-icon name="shield-check" class="size-4 text-money-600" />
                    <span>Conversão Exata 1:1</span>
                </h3>
                <p class="text-slate-600 leading-relaxed">Cada R$ 1,00 contratado e confirmado libera exatamente 1 crédito para uso em insumos do catálogo NexoSupri.</p>
            </div>
            <div class="space-y-1">
                <h3 class="font-semibold text-ink flex items-center gap-1.5">
                    <x-icon name="lock" class="size-4 text-accent-600" />
                    <span>Crédito Exclusivo (Modelo A)</span>
                </h3>
                <p class="text-slate-600 leading-relaxed">Não é moeda eletrônica nem saldo sacável. Não permite transferência livre entre empresas distintas.</p>
            </div>
            <div class="space-y-1">
                <h3 class="font-semibold text-ink flex items-center gap-1.5">
                    <x-icon name="clipboard-list" class="size-4 text-ink" />
                    <span>Governança Multiusuário</span>
                </h3>
                <p class="text-slate-600 leading-relaxed">Gestores da empresa definem limites por solicitante e aprovam pedidos antes do débito definitivo.</p>
            </div>
        </section>
    </div>
</x-app-layout>
