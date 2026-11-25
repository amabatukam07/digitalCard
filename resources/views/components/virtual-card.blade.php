@props([
    'company' => null,
    'wallet' => null,
    'planName' => null,
    'interactive' => false,
])
@php
    $isDemo = $company === null || $wallet === null;
    $suffix = $wallet ? str_pad((string) (((int) $wallet->id * 7919) % 10000), 4, '0', STR_PAD_LEFT) : '4021';
    $maskedCode = 'NXSP •••• •••• '.$suffix;
    $balanceFormatted = $wallet ? number_format((float) $wallet->balance, 2, ',', '.') : '1.000,00';
    $acquiredFormatted = $wallet ? number_format((float) $wallet->total_acquired, 2, ',', '.') : '1.000,00';
    $dailyLimitText = $wallet && $wallet->daily_limit !== null
        ? number_format((float) $wallet->daily_limit, 2, ',', '.').' cr.'
        : 'Sem teto diário';
    $holder = $company ? ($company->trade_name ?: $company->legal_name) : 'Sua Empresa (Modelo A)';
    $cnpj = $company?->cnpj ?: '00.000.000/0001-00';
    $isActive = $wallet ? (bool) $wallet->is_active : true;
@endphp

<div x-data="{
        flipped: false,
        showBalance: true,
        rx: 0,
        ry: 0,
        gx: 50,
        gy: 50,
        hovering: false,
        onMove(e) {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            const rect = this.$el.getBoundingClientRect();
            const px = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
            const py = Math.min(1, Math.max(0, (e.clientY - rect.top) / rect.height));
            this.gx = Math.round(px * 100);
            this.gy = Math.round(py * 100);
            this.ry = Number(((px - 0.5) * 14).toFixed(2));
            this.rx = Number(((0.5 - py) * 12).toFixed(2));
            this.hovering = true;
        },
        onLeave() {
            this.hovering = false;
            this.rx = 0;
            this.ry = 0;
        }
     }"
     class="virtual-card-shell relative cursor-pointer select-none"
     :class="{ 'animate-subtle-float': {{ $interactive ? 'false' : '!hovering && !flipped' }} }"
     style="perspective: 1200px;"
     @mousemove="onMove($event)"
     @mouseleave="onLeave()"
     @click="flipped = !flipped"
     role="button"
     tabindex="0"
     aria-label="Cartão Corporativo NexoSupri — clique para girar"
     @keydown.enter.prevent="flipped = !flipped"
     @keydown.space.prevent="flipped = !flipped">

    {{-- Halo luminoso ambiente --}}
    <div aria-hidden="true"
         class="absolute -inset-2 rounded-2xl bg-gradient-to-tr from-accent-500/25 via-transparent to-money-500/20 blur-xl card-ambient-halo pointer-events-none"></div>

    {{-- Wrapper de inclinação 3D interativa --}}
    <div :style="`transform: rotateX(${rx}deg) rotateY(${ry}deg); transition: transform ${hovering ? '180ms ease-out' : '550ms cubic-bezier(0.22, 1, 0.36, 1)'}; transform-style: preserve-3d;`">
        {{-- Wrapper de giro frente/verso --}}
        <div class="relative aspect-[1.586/1] w-full transition-transform duration-700"
             :style="flipped ? 'transform: rotateY(180deg); transform-style: preserve-3d;' : 'transform-style: preserve-3d;'">

            {{-- Frente --}}
            <div class="absolute inset-0 overflow-hidden rounded-xl border border-slate-700/80 bg-gradient-to-br from-[#1C1917] via-[#14110F] to-[#0A0908] p-4 sm:p-6 text-paper shadow-float"
                 style="backface-visibility: hidden;">

                {{-- Varredura metálica contínua --}}
                <div aria-hidden="true"
                     class="pointer-events-none absolute inset-y-0 -left-1/2 w-1/2 card-shimmer-sweep"></div>

                {{-- Brilho especular interativo seguindo o cursor --}}
                <div aria-hidden="true"
                     :style="`background: radial-gradient(280px circle at ${gx}% ${gy}%, rgba(255, 244, 230, 0.2), transparent 65%); opacity: ${hovering ? 1 : 0}; transition: opacity 250ms ease;`"
                     class="pointer-events-none absolute inset-0"></div>

                <div class="relative flex h-full flex-col justify-between">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <div class="h-8 w-8 rounded-md bg-paper text-ink flex items-center justify-center font-serif text-base font-bold">
                                NS
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="block font-serif text-lg text-paper leading-none">NexoSupri</span>
                                    @if($planName)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-slate-800 border border-slate-600 text-[10px] font-semibold uppercase tracking-wide text-accent-400">
                                            @if($interactive)
                                                <x-icon name="layers" class="size-3" />
                                            @endif
                                            <span>{{ $planName }}</span>
                                        </span>
                                    @endif
                                </div>
                                <span class="block text-xs text-slate-300 mt-0.5">Cartão Corporativo · Modelo A</span>
                            </div>
                        </div>

                        <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $isActive ? 'text-money-400' : 'text-danger-400' }}">
                            <span class="relative flex size-2">
                                @if($isActive)
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-money-400 opacity-75"></span>
                                @endif
                                <span class="relative inline-flex rounded-full size-2 {{ $isActive ? 'bg-money-500' : 'bg-danger-500' }}"></span>
                            </span>
                            <span>{{ $isActive ? 'Ativo' : 'Bloqueado' }}</span>
                        </span>
                    </div>

                    <div class="flex items-center justify-between py-1">
                        <div class="h-8 w-11 rounded-sm border border-slate-600 bg-slate-800 grid grid-cols-3 grid-rows-2 gap-0.5 p-0.5">
                            <div class="border-b border-r border-slate-600"></div>
                            <div class="border-b border-slate-600"></div>
                            <div class="border-b border-l border-slate-600"></div>
                            <div class="border-r border-slate-600"></div>
                            <div></div>
                            <div class="border-l border-slate-600"></div>
                        </div>

                        <div class="text-right">
                            <span class="block text-[11px] text-slate-300">
                                {{ $interactive ? 'Saldo em créditos (1:1)' : ($isDemo ? 'Conversão oficial 1:1' : 'Saldo em créditos') }}
                            </span>
                            <span class="font-mono text-lg font-semibold tabular-nums text-money-400"
                                  @if($interactive) x-text="showBalance ? '{{ $balanceFormatted }} cr.' : '••••••'" @endif>
                                {{ $isDemo ? 'R$ 1.000 = 1.000 cr.' : $balanceFormatted.' cr.' }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <p class="mb-2.5 font-mono text-base sm:text-xl font-semibold tabular-nums text-paper">{{ $maskedCode }}</p>

                        <div class="flex items-end justify-between text-xs border-t border-slate-800 pt-2.5 gap-2">
                            <div class="min-w-0">
                                <span class="block text-[11px] text-slate-400">{{ $interactive ? 'Empresa contratante' : 'Empresa titular' }}</span>
                                <span class="text-xs font-medium text-paper truncate block">{{ $holder }}</span>
                            </div>

                            <div class="text-right shrink-0">
                                <span class="block text-[11px] text-slate-400">{{ $interactive ? 'Poder de compra' : 'Regra' }}</span>
                                <span class="font-mono text-xs tabular-nums text-paper">{{ $interactive ? 'R$ '.$balanceFormatted : '1 cr. = R$ 1,00' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Verso --}}
            <div class="absolute inset-0 overflow-hidden rounded-xl border border-slate-700/80 bg-gradient-to-br from-[#14110F] via-[#1C1917] to-[#0A0908] p-4 sm:p-6 text-paper shadow-float"
                 style="backface-visibility: hidden; transform: rotateY(180deg);">
                <div class="relative flex h-full flex-col justify-between">
                    <div class="flex items-center justify-between text-xs text-slate-300">
                        <span class="font-serif text-base text-paper">Regras do Modelo A</span>
                        <span class="font-mono text-[11px] px-2 py-0.5 rounded bg-slate-800 border border-slate-700">1 crédito = R$ 1,00</span>
                    </div>

                    <div class="my-1 h-7 bg-slate-950 -mx-4 sm:-mx-6 flex items-center px-4 sm:px-6 text-[10px] sm:text-[11px] font-mono text-slate-400">
                        <span class="truncate">USO EXCLUSIVO NO CATÁLOGO NEXOSUPRI · SEM SAQUE OU TRANSFERÊNCIA</span>
                    </div>

                    <div class="grid grid-cols-3 gap-3 border-t border-slate-800 pt-3 text-xs">
                        <div class="min-w-0">
                            <span class="block text-[11px] text-slate-400">CNPJ</span>
                            <span class="font-mono text-xs font-semibold tabular-nums text-paper truncate block">{{ $cnpj }}</span>
                        </div>
                        <div>
                            <span class="block text-[11px] text-slate-400">Limite diário</span>
                            <span class="font-mono text-xs font-semibold tabular-nums text-paper">{{ $dailyLimitText }}</span>
                        </div>
                        <div class="text-right">
                            <span class="block text-[11px] text-slate-400">Adquirido</span>
                            <span class="font-mono text-xs font-semibold tabular-nums text-money-400">{{ $acquiredFormatted }} cr.</span>
                        </div>
                    </div>

                    <p class="text-[11px] text-center text-slate-400">Clique no cartão corporativo para voltar</p>
                </div>
            </div>
        </div>
    </div>
</div>
