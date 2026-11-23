<x-app-layout :metaTitle="'Meu Cartão Virtual'" :metaDescription="'Consulte seu saldo, recarregue via PIX instantâneo e acompanhe o extrato do seu cartão virtual DigitalCard.'">
    @php
        $pixKey = (string) config('digitalcard.pix.key', 'pix@digitalcard.com.br');
        $typeLabels = [
            'recarga' => ['Recarga PIX', 'bg-money-50 text-money-700 border-money-200'],
            'compra' => ['Compra Loja', 'bg-danger-50 text-danger-700 border-danger-200'],
            'cashback' => ['Cashback', 'bg-accent-50 text-accent-800 border-accent-200'],
            'estorno' => ['Estorno', 'bg-paper text-slate-700 border-line'],
            'estorno_cashback' => ['Estorno Cashback', 'bg-danger-50 text-danger-700 border-danger-200'],
            'transferencia_enviada' => ['Transferência Enviada', 'bg-danger-50 text-danger-700 border-danger-200'],
            'transferencia_recebida' => ['Transferência Recebida', 'bg-money-50 text-money-700 border-money-200'],
            'gift_card_compra' => ['Vale-Presente Emitido', 'bg-danger-50 text-danger-700 border-danger-200'],
            'gift_card_resgate' => ['Vale-Presente Resgatado', 'bg-money-50 text-money-700 border-money-200'],
            'cofrinho_deposito' => ['Reserva no Cofrinho', 'bg-danger-50 text-danger-700 border-danger-200'],
            'cofrinho_resgate' => ['Resgate do Cofrinho', 'bg-money-50 text-money-700 border-money-200'],
        ];
    @endphp

    <div class="app-shell py-8 space-y-8"
         x-data="{
            showBalance: true,
            flipped: false,
            copied: false,
            toastMessage: 'Copiado para a área de transferência!',
            dragging: false,
            rechargeAmount: 250,
            receiptName: '',
            receiptSize: '',
            receiptPreviewUrl: '',
            isPdf: false,
            txFilter: 'todas',
            cardBalanceFormatted: @js(number_format((float) $card->balance, 2, ',', '.')),
            maskedNumber: @js($card->masked_number),
            cardNumber: @js($card->masked_number),
            cvvCode: '•••',
            isRevealed: false,
            revealSecondsLeft: 0,
            revealTimer: null,
            revealPin: '',
            revealError: '',
            revealLoading: false,
            pixRemainingSeconds: {{ $activePixCharge && $activePixCharge->expires_at ? max(0, (int) now()->diffInSeconds($activePixCharge->expires_at, false)) : 0 }},
            pixStatus: @js($activePixCharge->status ?? ''),
            init() {
                @if($activePixCharge)
                    setInterval(() => {
                        if (this.pixRemainingSeconds > 0 && this.pixStatus === 'pendente') {
                            this.pixRemainingSeconds--;
                        }
                    }, 1000);

                    const pollTimer = setInterval(async () => {
                        if (this.pixStatus !== 'pendente') {
                            clearInterval(pollTimer);
                            return;
                        }
                        try {
                            const res = await fetch(@js(route('card.pix.status', $activePixCharge)), {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            if (!res.ok) return;
                            const data = await res.json();
                            this.pixRemainingSeconds = data.remaining_seconds ?? this.pixRemainingSeconds;
                            if (data.status === 'aprovado') {
                                this.pixStatus = 'aprovado';
                                this.cardBalanceFormatted = data.card_balance_formatted;
                                this.showToast('Pagamento PIX confirmado! Saldo atualizado para R$ ' + data.card_balance_formatted);
                                clearInterval(pollTimer);
                                setTimeout(() => window.location.reload(), 1500);
                            }
                        } catch (e) {}
                    }, 5000);
                @endif
            },
            hideRevealedData() {
                if (this.revealTimer) {
                    clearInterval(this.revealTimer);
                    this.revealTimer = null;
                }
                this.cardNumber = this.maskedNumber;
                this.cvvCode = '•••';
                this.isRevealed = false;
                this.revealSecondsLeft = 0;
            },
            startAutoMaskCountdown() {
                if (this.revealTimer) {
                    clearInterval(this.revealTimer);
                }
                this.revealSecondsLeft = 30;
                this.revealTimer = setInterval(() => {
                    if (this.revealSecondsLeft > 1) {
                        this.revealSecondsLeft--;
                    } else {
                        this.hideRevealedData();
                    }
                }, 1000);
            },
            formatCountdown(sec) {
                const m = String(Math.floor(sec / 60)).padStart(2, '0');
                const s = String(sec % 60).padStart(2, '0');
                return m + 'm : ' + s + 's';
            },
            showToast(msg) {
                this.toastMessage = msg;
                this.copied = true;
                setTimeout(() => this.copied = false, 3500);
            },
            copyText(text, label = 'Copiado para a área de transferência!') {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text);
                }
                this.showToast(label);
            },
            async revealCardData() {
                this.revealError = '';
                this.revealLoading = true;
                try {
                    const res = await fetch(@js(route('card.reveal-cvv')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': @js(csrf_token()),
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ pin: this.revealPin })
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.revealError = data.errors?.pin?.[0] || data.message || 'PIN inválido.';
                    } else {
                        this.cardNumber = data.card_number;
                        this.cvvCode = data.cvv;
                        this.isRevealed = true;
                        this.revealPin = '';
                        this.startAutoMaskCountdown();
                        this.showToast('Número completo e CVV revelados por 30 segundos!');
                    }
                } catch (e) {
                    this.revealError = 'Erro ao validar o PIN.';
                } finally {
                    this.revealLoading = false;
                }
            },
            setQuickAmount(val) {
                this.rechargeAmount = val;
            },
            handleFile(file) {
                if (! file) {
                    this.receiptName = '';
                    this.receiptSize = '';
                    this.receiptPreviewUrl = '';
                    this.isPdf = false;
                    return;
                }
                this.receiptName = file.name;
                this.receiptSize = file.size >= 1048576
                    ? (file.size / 1048576).toFixed(2) + ' MB'
                    : (file.size / 1024).toFixed(1) + ' KB';
                this.isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
                this.receiptPreviewUrl = (! this.isPdf && file.type.startsWith('image/'))
                    ? URL.createObjectURL(file)
                    : '';
            },
            handleDrop(event) {
                this.dragging = false;
                const file = event.dataTransfer.files[0];
                if (! file) return;
                const transfer = new DataTransfer();
                transfer.items.add(file);
                this.$refs.receipt.files = transfer.files;
                this.handleFile(file);
            }
         }">

        <div x-show="copied" x-cloak
             x-transition
             role="status"
             aria-live="polite"
             class="fixed bottom-6 right-6 z-50 flex items-center gap-2 rounded-lg border border-slate-800 bg-ink px-4 py-3 text-xs font-medium text-paper shadow-sm">
            <x-icon name="check" class="size-4 text-money-400" />
            <span x-text="toastMessage"></span>
        </div>

        <div class="flex flex-col justify-between gap-4 border-b border-line pb-5 md:flex-row md:items-end">
            <div>
                <x-eyebrow>Carteira digital</x-eyebrow>
                <h1 class="mt-1 font-serif text-3xl text-ink">Meu Cartão Virtual</h1>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-medium {{ $card->is_active ? 'border-money-200 bg-money-50 text-money-800' : 'border-danger-200 bg-danger-50 text-danger-800' }}">
                    <span class="size-2 rounded-full {{ $card->is_active ? 'bg-money-500' : 'bg-danger-500' }}"></span>
                    {{ $card->is_active ? 'Cartão ativo para compras' : 'Cartão bloqueado temporariamente' }}
                </span>
                <a href="{{ route('catalog.index') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-ink hover:bg-slate-800 px-4 py-2.5 text-xs font-semibold text-paper transition">
                    <span>Ir às compras</span>
                    <x-icon name="arrow-right" class="size-4" />
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 items-start gap-8 lg:grid-cols-12">
            <div class="space-y-5 lg:col-span-5">
                <div class="max-w-[460px]">
                    <x-virtual-card :card="$card" :interactive="true" :tierLabel="$loyaltySummary['tier_label'] ?? null" />
                </div>

                <div class="grid grid-cols-3 gap-2.5">
                    <button @click="isRevealed ? copyText(cardNumber, 'Número copiado!') : showToast('Confirme seu PIN abaixo para revelar e copiar o número completo.')"
                            type="button"
                            class="flex items-center justify-center gap-1.5 rounded-lg border border-line bg-surface px-3 py-2.5 text-xs font-medium text-ink transition hover:bg-paper-dim">
                        <x-icon name="copy" class="size-3.5 text-slate-500" />
                        <span>Copiar Nº</span>
                    </button>

                    <button @click="showBalance = ! showBalance" type="button"
                            class="flex items-center justify-center gap-1.5 rounded-lg border border-line bg-surface px-3 py-2.5 text-xs font-medium text-ink transition hover:bg-paper-dim">
                        <x-icon name="eye" class="size-3.5 text-slate-500" />
                        <span x-text="showBalance ? 'Ocultar saldo' : 'Mostrar saldo'"></span>
                    </button>

                    <button @click="flipped = ! flipped" type="button"
                            class="flex items-center justify-center gap-1.5 rounded-lg border border-line bg-surface px-3 py-2.5 text-xs font-medium text-ink transition hover:bg-paper-dim">
                        <x-icon name="rotate-ccw" class="size-3.5 text-slate-500" />
                        <span>Virar cartão</span>
                    </button>
                </div>

                @if(isset($loyaltySummary))
                    @php
                        $tierEnum = $loyaltySummary['tier'];
                    @endphp
                    <div class="rounded-xl border border-line bg-surface p-5 space-y-3.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <span class="h-9 w-9 rounded-lg bg-paper border border-line flex items-center justify-center shrink-0">
                                    <x-icon name="crown" class="size-4 text-accent-600" />
                                </span>
                                <div>
                                    <h2 class="text-sm font-semibold text-ink">Clube DigitalCard</h2>
                                    <p class="text-xs text-slate-600 tabular-nums">
                                        Compras entregues: R$ {{ number_format($loyaltySummary['delivered_spend'], 2, ',', '.') }}
                                    </p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-semibold {{ $tierEnum->badgeClasses() }}">
                                Nível {{ $loyaltySummary['tier_label'] }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5 text-xs">
                            <div class="rounded-lg border border-line bg-paper p-3">
                                <span class="text-slate-500 block">Cashback bônus</span>
                                <span class="font-mono font-semibold tabular-nums text-money-700 mt-0.5 block">
                                    +{{ rtrim(rtrim(number_format($loyaltySummary['bonus_cashback_percent'], 2, ',', '.'), '0'), ',') }}% por item
                                </span>
                            </div>
                            <div class="rounded-lg border border-line bg-paper p-3">
                                <span class="text-slate-500 block">Frete grátis a partir de</span>
                                <span class="font-mono font-semibold tabular-nums text-ink mt-0.5 block">
                                    {{ $loyaltySummary['free_shipping_min_cents'] === 0 ? 'Sempre grátis' : 'R$ '.number_format($loyaltySummary['free_shipping_min'], 0, ',', '.') }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-center justify-between text-slate-600">
                                @if($loyaltySummary['next_tier_label'])
                                    <span>Faltam <strong class="font-mono tabular-nums text-ink">R$ {{ number_format($loyaltySummary['remaining_to_next'], 2, ',', '.') }}</strong> para <strong>{{ $loyaltySummary['next_tier_label'] }}</strong></span>
                                @else
                                    <span class="font-medium text-money-700">Você atingiu o nível máximo do Clube DigitalCard!</span>
                                @endif
                                <span class="font-mono tabular-nums font-semibold text-ink">{{ $loyaltySummary['progress_percent'] }}%</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-paper-dim overflow-hidden">
                                <div class="h-full bg-accent-500 rounded-full transition-all" style="width: {{ $loyaltySummary['progress_percent'] }}%"></div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="rounded-xl border border-line bg-surface p-5 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <h2 class="text-sm font-semibold text-ink">Revelar número completo e CVV</h2>
                            <p class="text-xs text-slate-600 mt-0.5">Digite seu PIN de 4 dígitos para ver os dados por 30 segundos.</p>
                        </div>
                    </div>

                    <form action="{{ route('card.reveal-cvv') }}" method="POST" @submit.prevent="revealCardData()" class="flex flex-col sm:flex-row gap-2">
                        @csrf
                        <label for="reveal_pin_field" class="sr-only">PIN de 4 dígitos</label>
                        <input id="reveal_pin_field" type="password" name="pin" x-model="revealPin" inputmode="numeric" maxlength="4" required
                               placeholder="PIN de 4 dígitos"
                               class="flex-1 rounded-lg border-line text-xs font-mono py-2.5 px-3">
                        <button type="submit" :disabled="revealLoading"
                                class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-ink hover:bg-slate-800 text-paper font-semibold text-xs px-4 py-2.5 transition">
                            <x-icon name="lock" class="size-3.5" />
                            <span x-text="revealLoading ? 'Verificando...' : 'Revelar com PIN'">Revelar com PIN</span>
                        </button>
                    </form>

                    <p x-show="revealError" x-cloak class="text-xs font-medium text-danger-600" x-text="revealError"></p>

                    <div x-show="isRevealed" x-cloak class="rounded-lg border border-money-200 bg-money-50 p-3 text-xs space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-ink">Nº do cartão: <strong class="font-mono tabular-nums" x-text="cardNumber"></strong></span>
                            <button @click="copyText(cardNumber, 'Número completo copiado!')" type="button" class="font-medium text-money-800 underline">Copiar</button>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="font-medium text-ink">CVV: <strong class="font-mono tabular-nums text-money-900" x-text="cvvCode"></strong></p>
                            <span class="font-mono text-slate-600 tabular-nums" x-text="'Ocultando em ' + revealSecondsLeft + 's'"></span>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 rounded-xl border border-line bg-surface p-5">
                    <div class="border-b border-line pb-3">
                        <h2 class="text-sm font-semibold text-ink">Segurança e controle do cartão</h2>
                        <p class="text-xs text-slate-600 mt-0.5">Bloqueio temporário, limite diário e senha PIN de 4 dígitos.</p>
                    </div>

                    <form action="{{ route('card.toggle-block') }}" method="POST" class="space-y-2">
                        @csrf
                        @method('PATCH')
                        @if(! $card->is_active && $card->security_pin)
                            <div>
                                <label for="unblock_pin_input" class="block text-xs font-medium text-slate-700 mb-1">Confirme seu PIN de 4 dígitos para desbloquear</label>
                                <input id="unblock_pin_input" type="password" name="pin" inputmode="numeric" maxlength="4" required placeholder="PIN atual (4 dígitos)"
                                       class="block w-full rounded-lg border-line text-xs py-2 font-mono">
                            </div>
                        @endif
                        <button type="submit"
                                class="flex w-full items-center justify-between rounded-lg border px-4 py-2.5 text-left text-xs font-medium transition {{ $card->is_active ? 'border-danger-200 bg-danger-50 text-danger-700 hover:bg-danger-100' : 'border-money-200 bg-money-50 text-money-700 hover:bg-money-100' }}">
                            <span>{{ $card->is_active ? 'Bloquear cartão temporariamente' : 'Desbloquear cartão agora' }}</span>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-surface">{{ $card->is_active ? 'Bloquear' : 'Ativar' }}</span>
                        </button>
                    </form>

                    <form action="{{ route('card.update-limit') }}" method="POST" class="space-y-2.5 border-t border-line pt-4">
                        @csrf
                        @method('PATCH')
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-slate-700">Limite diário de compras</span>
                            <span class="font-mono tabular-nums text-slate-500">
                                Gasto hoje: R$ {{ number_format($dailySpent ?? 0, 2, ',', '.') }}
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <input id="daily_limit_input" type="number" step="0.01" min="10.00" max="999999.99" name="daily_limit"
                                   value="{{ old('daily_limit', $card->daily_limit !== null ? number_format((float) $card->daily_limit, 2, '.', '') : '') }}"
                                   placeholder="Sem limite (opcional)"
                                   aria-label="Limite diário em reais"
                                   class="flex-1 rounded-lg border-line text-xs font-mono tabular-nums py-2 px-3">
                            <button type="submit" class="rounded-lg border border-line bg-paper hover:border-ink px-3.5 py-2 text-xs font-semibold text-ink transition">
                                Salvar limite
                            </button>
                        </div>
                    </form>

                    <form action="{{ route('card.update-pin') }}" method="POST" class="space-y-3 border-t border-line pt-4">
                        @csrf
                        @method('PATCH')

                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-slate-700">PIN de 4 dígitos</span>
                            <span class="rounded px-2 py-0.5 text-xs font-medium {{ $card->security_pin ? 'bg-money-50 text-money-800 border border-money-200' : 'bg-accent-50 text-accent-900 border border-accent-200' }}">
                                {{ $card->security_pin ? 'PIN configurado' : 'Pendente (obrigatório para comprar)' }}
                            </span>
                        </div>

                        @if ($card->security_pin)
                            <div>
                                <label for="current_pin_input" class="sr-only">PIN atual</label>
                                <input id="current_pin_input" type="password" name="current_pin" inputmode="numeric" maxlength="4" placeholder="PIN atual (4 dígitos)"
                                       class="block w-full rounded-lg border-line text-xs py-2">
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="new_pin_input" class="sr-only">Novo PIN</label>
                                <input id="new_pin_input" type="password" name="pin" inputmode="numeric" maxlength="4" required placeholder="Novo PIN (4 dígitos)"
                                       class="block w-full rounded-lg border-line text-xs py-2">
                            </div>
                            <div>
                                <label for="confirm_pin_input" class="sr-only">Confirmar novo PIN</label>
                                <input id="confirm_pin_input" type="password" name="pin_confirmation" inputmode="numeric" maxlength="4" required placeholder="Confirmar PIN"
                                       class="block w-full rounded-lg border-line text-xs py-2">
                            </div>
                        </div>

                        <button type="submit"
                                class="w-full rounded-lg bg-ink px-3 py-2.5 text-xs font-semibold text-paper transition hover:bg-slate-800">
                            {{ $card->security_pin ? 'Atualizar PIN de segurança' : 'Cadastrar PIN de segurança' }}
                        </button>
                    </form>

                    @if($card->security_pin)
                        <form action="{{ route('card.rotate-cvv') }}" method="POST" class="space-y-2.5 border-t border-line pt-4">
                            @csrf
                            <div>
                                <span class="text-xs font-medium text-slate-700 block">Rotacionar código CVV</span>
                                <span class="text-xs text-slate-500">Gera um novo CVV dinâmico mediante confirmação do PIN.</span>
                            </div>
                            <div class="flex gap-2">
                                <input type="password" name="pin" inputmode="numeric" maxlength="4" required placeholder="PIN (4 dígitos)"
                                       aria-label="PIN para rotacionar CVV"
                                       class="flex-1 rounded-lg border-line text-xs font-mono py-2 px-3">
                                <button type="submit" class="rounded-lg border border-line bg-paper hover:border-ink px-3.5 py-2 text-xs font-semibold text-ink transition">
                                    Gerar novo CVV
                                </button>
                            </div>
                        </form>
                    @endif
                </div>

                <div class="rounded-xl border border-line bg-surface p-5 space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-line pb-3">
                        <span class="h-9 w-9 rounded-lg bg-paper border border-line flex items-center justify-center shrink-0">
                            <x-icon name="send" class="size-4 text-ink" />
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-ink">Transferência instantânea P2P</h2>
                            <p class="text-xs text-slate-600">Envie saldo na hora para outro usuário DigitalCard pelo e-mail.</p>
                        </div>
                    </div>

                    <form action="{{ route('card.transfer') }}" method="POST" class="space-y-3 text-xs">
                        @csrf
                        <div>
                            <label for="transfer_recipient_email" class="block font-medium text-slate-700 mb-1">E-mail do destinatário</label>
                            <input id="transfer_recipient_email" type="email" name="recipient_email" value="{{ old('recipient_email') }}"
                                   autocorrect="off" autocapitalize="off" spellcheck="false" required
                                   placeholder="marina@digitalcard.com"
                                   class="block w-full rounded-lg border-line bg-paper py-2 px-3 text-xs">
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <label for="transfer_amount" class="block font-medium text-slate-700 mb-1">Valor (R$)</label>
                                <input id="transfer_amount" type="number" name="amount" step="0.01" min="1.00" max="10000.00" inputmode="decimal"
                                       value="{{ old('amount') }}" required placeholder="100.00"
                                       class="block w-full rounded-lg border-line bg-paper py-2 px-3 font-mono tabular-nums text-xs">
                            </div>
                            <div>
                                <label for="transfer_pin" class="block font-medium text-slate-700 mb-1">PIN de segurança</label>
                                <input id="transfer_pin" type="password" name="pin" inputmode="numeric" maxlength="4" required placeholder="••••"
                                       class="block w-full rounded-lg border-line bg-paper py-2 px-3 font-mono text-xs">
                            </div>
                        </div>

                        <div>
                            <label for="transfer_note" class="block font-medium text-slate-700 mb-1">Mensagem (opcional)</label>
                            <input id="transfer_note" type="text" name="note" maxlength="140" value="{{ old('note') }}"
                                   placeholder="Ex.: Divisão da compra do escritório"
                                   class="block w-full rounded-lg border-line bg-paper py-2 px-3 text-xs">
                        </div>

                        <button type="submit"
                                class="w-full rounded-lg bg-ink hover:bg-slate-800 px-4 py-2.5 text-xs font-semibold text-paper transition flex items-center justify-center gap-1.5">
                            <x-icon name="send" class="size-3.5" />
                            <span>Enviar transferência agora</span>
                        </button>
                    </form>
                </div>
            </div>

            <div class="space-y-6 lg:col-span-7">
                <div class="rounded-xl border border-line bg-surface p-6 space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-line pb-4">
                        <div>
                            <x-eyebrow>Recarga instantânea</x-eyebrow>
                            <h2 class="mt-1 text-lg font-semibold text-ink">Recarregar saldo via PIX</h2>
                            <p class="text-xs text-slate-600 mt-0.5">Escolha o valor, gere o PIX e pague com o QR Code ou o código copia e cola.</p>
                        </div>
                        <span class="text-xs font-mono text-slate-600 bg-paper-dim px-2.5 py-1 rounded-md border border-line">
                            Chave: {{ $pixKey }}
                        </span>
                    </div>

                    <form action="{{ route('card.pix.generate') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label for="pix_recharge_amount" class="mb-2 block text-xs font-medium text-slate-700">
                                1. Escolha ou digite o valor da recarga PIX (R$)
                            </label>
                            <div class="grid grid-cols-5 gap-2 mb-2.5">
                                @foreach([100, 250, 500, 1000, 2500] as $preset)
                                    <button type="button" @click="setQuickAmount({{ $preset }})"
                                            :class="rechargeAmount == {{ $preset }} ? 'bg-ink text-paper border-ink' : 'bg-paper text-slate-700 border-line hover:bg-paper-dim'"
                                            class="py-2 px-2 rounded-lg border font-mono tabular-nums text-xs font-medium transition">
                                        +R$ {{ number_format($preset, 0, ',', '.') }}
                                    </button>
                                @endforeach
                            </div>

                            <div class="flex flex-col sm:flex-row gap-2.5">
                                <div class="relative flex-1">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                        <span class="text-sm font-medium text-slate-500">R$</span>
                                    </div>
                                    <input id="pix_recharge_amount" type="number" step="0.01" min="1.00" max="999999.99" name="amount" x-model="rechargeAmount" required
                                           class="block w-full rounded-lg border-line py-2.5 pl-11 font-mono tabular-nums text-base font-semibold text-ink focus:border-ink focus:ring-ink">
                                </div>
                                <button type="submit"
                                        class="rounded-lg bg-ink hover:bg-slate-800 px-5 py-3 text-xs font-semibold text-paper transition whitespace-nowrap">
                                    Gerar PIX agora
                                </button>
                            </div>
                        </div>
                    </form>

                    @if($activePixCharge)
                        <div class="rounded-xl border border-money-200 bg-money-50/60 p-5 space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-money-200 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex size-2 rounded-full bg-money-500"></span>
                                    <span class="text-xs font-medium text-money-950">
                                        Cobrança ativa: <code class="font-mono">{{ $activePixCharge->txid }}</code>
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="text-slate-700">Expira em:</span>
                                    <span class="rounded bg-ink px-2 py-0.5 font-mono tabular-nums font-medium text-paper"
                                          x-text="formatCountdown(pixRemainingSeconds)"></span>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row items-center gap-5">
                                <div class="shrink-0 rounded-xl border border-line bg-surface p-3">
                                    {!! $activePixQrSvg !!}
                                </div>

                                <div class="flex-1 space-y-3 text-xs w-full min-w-0">
                                    <div class="flex items-baseline justify-between">
                                        <span class="font-medium text-slate-700">Valor da cobrança:</span>
                                        <span class="font-mono tabular-nums text-xl font-semibold text-money-800">
                                            R$ {{ number_format((float) $activePixCharge->amount, 2, ',', '.') }}
                                        </span>
                                    </div>

                                    <div>
                                        <label for="pix_brcode_copy" class="block text-xs font-medium text-slate-700 mb-1">
                                            PIX copia e cola
                                        </label>
                                        <div class="flex gap-2">
                                            <input id="pix_brcode_copy" type="text" readonly value="{{ $activePixCharge->pix_payload }}"
                                                   class="flex-1 min-w-0 rounded-lg border-line bg-surface font-mono text-xs py-2 px-3 text-slate-800">
                                            <button @click="copyText(@js($activePixCharge->pix_payload), 'Código PIX copia e cola copiado!')"
                                                    type="button"
                                                    class="rounded-lg bg-ink hover:bg-slate-800 px-3.5 py-2 text-xs font-semibold text-paper transition shrink-0">
                                                Copiar
                                            </button>
                                        </div>
                                    </div>

                                    @if(! app()->isProduction())
                                        <form action="{{ route('webhooks.pix.simulate') }}" method="POST" class="pt-1">
                                            @csrf
                                            <input type="hidden" name="txid" value="{{ $activePixCharge->txid }}">
                                            <button type="submit"
                                                    class="w-full rounded-lg bg-money-600 hover:bg-money-700 px-4 py-3 text-xs font-semibold text-paper transition flex items-center justify-center gap-2">
                                                <x-icon name="qr-code" class="size-4" />
                                                <span>Confirmar pagamento PIX (Ambiente de Teste)</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    <details class="group rounded-lg border border-line bg-paper p-4 text-xs">
                        <summary class="cursor-pointer font-medium text-ink flex items-center justify-between list-none">
                            <span>Alternativa secundária: enviar comprovante manual (sujeito à aprovação do administrador)</span>
                            <x-icon name="chevron-down" class="size-4 text-slate-500 group-open:rotate-180 transition-transform" />
                        </summary>

                        <form action="{{ route('card.recharge') }}" method="POST" enctype="multipart/form-data" class="space-y-4 mt-4 pt-4 border-t border-line">
                            @csrf
                            <div>
                                <label for="manual_recharge_amount" class="mb-1 block text-xs font-medium text-slate-700">Valor do comprovante (R$)</label>
                                <input id="manual_recharge_amount" type="number" step="0.01" min="1.00" name="amount" x-model="rechargeAmount" required
                                       class="block w-full rounded-lg border-line py-2 px-3 font-mono tabular-nums text-sm font-semibold text-ink">
                            </div>

                            <div>
                                <label for="receipt-upload" class="mb-1 block text-xs font-medium text-slate-700">Arquivo do comprovante (JPG, PNG ou PDF até 5MB)</label>
                                <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required id="receipt-upload"
                                       x-ref="receipt"
                                       @change="handleFile($event.target.files[0])"
                                       class="block w-full text-xs text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-ink file:px-3 file:py-2 file:text-xs file:font-semibold file:text-paper">
                            </div>

                            <button type="submit"
                                    class="w-full rounded-lg bg-ink hover:bg-slate-800 px-4 py-2.5 text-xs font-semibold text-paper transition">
                                Enviar comprovante para análise
                            </button>
                        </form>
                    </details>
                </div>

                <div x-data="{ giftTab: 'emitir', giftAmount: 100 }" class="rounded-xl border border-line bg-surface p-6 space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line pb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="h-9 w-9 rounded-lg bg-paper border border-line flex items-center justify-center shrink-0">
                                <x-icon name="gift" class="size-4 text-accent-600" />
                            </span>
                            <div>
                                <x-eyebrow>Vales digitais</x-eyebrow>
                                <h2 class="text-base font-semibold text-ink">Vales-Presente (Gift Cards)</h2>
                            </div>
                        </div>

                        <div class="inline-flex rounded-lg bg-paper-dim p-1 text-xs font-medium">
                            <button type="button" @click="giftTab = 'emitir'"
                                    :class="giftTab === 'emitir' ? 'bg-surface text-ink shadow-xs' : 'text-slate-600 hover:text-ink'"
                                    class="rounded-md px-3 py-1.5 transition">
                                Emitir código
                            </button>
                            <button type="button" @click="giftTab = 'resgatar'"
                                    :class="giftTab === 'resgatar' ? 'bg-surface text-ink shadow-xs' : 'text-slate-600 hover:text-ink'"
                                    class="rounded-md px-3 py-1.5 transition">
                                Resgatar código
                            </button>
                        </div>
                    </div>

                    <form x-show="giftTab === 'emitir'" action="{{ route('gift-cards.store') }}" method="POST" class="space-y-4 text-xs">
                        @csrf
                        <div>
                            <label for="gift_card_amount" class="block font-medium text-slate-700 mb-1.5">Valor do vale-presente (R$ 25 a R$ 5.000)</label>
                            <div class="grid grid-cols-4 gap-2 mb-2">
                                @foreach([50, 100, 250, 500] as $gcPreset)
                                    <button type="button" @click="giftAmount = {{ $gcPreset }}"
                                            :class="giftAmount == {{ $gcPreset }} ? 'bg-ink text-paper border-ink' : 'bg-paper text-slate-700 border-line hover:bg-paper-dim'"
                                            class="py-1.5 px-2 rounded-lg border font-mono tabular-nums text-xs font-medium transition">
                                        R$ {{ $gcPreset }}
                                    </button>
                                @endforeach
                            </div>
                            <input id="gift_card_amount" type="number" name="amount" x-model="giftAmount" step="0.01" min="25.00" max="5000.00" inputmode="decimal" required
                                   class="block w-full rounded-lg border-line bg-paper py-2 px-3 font-mono tabular-nums text-sm font-semibold">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <div>
                                <label for="gift_recipient_name" class="block font-medium text-slate-700 mb-1">Para (opcional)</label>
                                <input id="gift_recipient_name" type="text" name="recipient_name" maxlength="120" value="{{ old('recipient_name') }}" placeholder="Nome de quem recebe"
                                       class="block w-full rounded-lg border-line bg-paper py-2 px-3 text-xs">
                            </div>
                            <div>
                                <label for="gift_recipient_email" class="block font-medium text-slate-700 mb-1">E-mail (opcional)</label>
                                <input id="gift_recipient_email" type="email" name="recipient_email" maxlength="180" value="{{ old('recipient_email') }}"
                                       autocorrect="off" autocapitalize="off" spellcheck="false" placeholder="pessoa@exemplo.com"
                                       class="block w-full rounded-lg border-line bg-paper py-2 px-3 text-xs">
                            </div>
                            <div>
                                <label for="gift_issue_pin" class="block font-medium text-slate-700 mb-1">PIN de segurança</label>
                                <input id="gift_issue_pin" type="password" name="pin" inputmode="numeric" maxlength="4" required placeholder="••••"
                                       class="block w-full rounded-lg border-line bg-paper py-2 px-3 font-mono text-xs">
                            </div>
                        </div>

                        <div>
                            <label for="gift_message" class="block font-medium text-slate-700 mb-1">Dedicatória (opcional)</label>
                            <input id="gift_message" type="text" name="message" maxlength="255" value="{{ old('message') }}" placeholder="Parabéns! Aproveite seus produtos favoritos na DigitalCard."
                                   class="block w-full rounded-lg border-line bg-paper py-2 px-3 text-xs">
                        </div>

                        <button type="submit"
                                class="w-full rounded-lg bg-ink hover:bg-slate-800 px-4 py-2.5 text-xs font-semibold text-paper transition">
                            Gerar Vale-Presente com saldo do cartão
                        </button>
                    </form>

                    <form x-show="giftTab === 'resgatar'" x-cloak action="{{ route('gift-cards.redeem') }}" method="POST" class="space-y-3 text-xs">
                        @csrf
                        <div>
                            <label for="gift_redeem_code" class="block font-medium text-slate-700 mb-1">Código do Vale-Presente</label>
                            <input id="gift_redeem_code" type="text" name="code" value="{{ old('code') }}"
                                   autocorrect="off" autocapitalize="characters" spellcheck="false" required maxlength="24"
                                   placeholder="DCGC-XXXX-XXXX-XXXX"
                                   class="block w-full rounded-lg border-line bg-paper py-2.5 px-3 font-mono uppercase tracking-wider text-sm">
                        </div>
                        <button type="submit"
                                class="w-full rounded-lg bg-money-600 hover:bg-money-700 px-4 py-2.5 text-xs font-semibold text-paper transition">
                            Resgatar valor para minha carteira
                        </button>
                    </form>

                    @if(isset($sentGiftCards) && $sentGiftCards->isNotEmpty())
                        <div class="border-t border-line pt-4 space-y-2 text-xs">
                            <h3 class="font-semibold text-ink">Vales emitidos por você</h3>
                            <div class="divide-y divide-line rounded-lg border border-line bg-paper overflow-hidden">
                                @foreach($sentGiftCards as $gc)
                                    <div class="px-3.5 py-2.5 flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <code class="font-mono font-semibold text-ink">{{ $gc->code }}</code>
                                            <button type="button" @click="copyText(@js($gc->code), 'Código do vale copiado!')" class="text-slate-500 hover:text-ink" title="Copiar código">
                                                <x-icon name="copy" class="size-3.5" />
                                            </button>
                                            @if($gc->recipient_name)
                                                <span class="text-slate-500 truncate">→ {{ $gc->recipient_name }}</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <span class="font-mono tabular-nums font-semibold text-ink">R$ {{ number_format((float) $gc->initial_amount, 2, ',', '.') }}</span>
                                            <span class="px-2 py-0.5 rounded border text-[11px] font-medium {{ $gc->status === 'active' ? 'border-money-200 bg-money-50 text-money-800' : 'border-line bg-surface text-slate-600' }}">
                                                {{ $gc->status === 'active' ? 'Ativo' : ($gc->status === 'redeemed' ? 'Resgatado' : 'Expirado') }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="rounded-xl border border-line bg-surface p-6 space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line pb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="h-9 w-9 rounded-lg bg-paper border border-line flex items-center justify-center shrink-0">
                                <x-icon name="piggy-bank" class="size-4 text-money-600" />
                            </span>
                            <div>
                                <x-eyebrow>Planejamento financeiro</x-eyebrow>
                                <h2 class="text-base font-semibold text-ink">Cofrinho DigitalCard (Metas de Compra)</h2>
                            </div>
                        </div>

                        <div class="text-right">
                            <span class="text-[11px] text-slate-500 block">Total guardado</span>
                            <span class="font-mono text-base font-bold tabular-nums text-money-700">
                                R$ {{ number_format((float) ($savingsGoalsTotal ?? 0), 2, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <form action="{{ route('goals.store') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 text-xs bg-paper p-4 rounded-xl border border-line">
                        @csrf
                        @if(! empty($goalPresetProduct))
                            <input type="hidden" name="product_id" value="{{ $goalPresetProduct->id }}">
                        @endif
                        <div class="sm:col-span-6">
                            <label for="goal_name_input" class="block font-medium text-slate-700 mb-1">Nome da meta</label>
                            <input id="goal_name_input" type="text" name="name" required maxlength="120"
                                   value="{{ old('name', ! empty($goalPresetProduct) ? Str::limit('Meta: '.$goalPresetProduct->name, 110) : '') }}"
                                   placeholder="Ex.: Setup Escritório ou Notebook"
                                   class="block w-full rounded-lg border-line bg-surface py-2 px-3 text-xs">
                        </div>
                        <div class="sm:col-span-3">
                            <label for="goal_target_input" class="block font-medium text-slate-700 mb-1">Valor-alvo (R$)</label>
                            <input id="goal_target_input" type="number" name="target_amount" step="0.01" min="10.00" max="100000.00" inputmode="decimal" required
                                   value="{{ old('target_amount', ! empty($goalPresetProduct) ? number_format((float) $goalPresetProduct->price, 2, '.', '') : '') }}"
                                   placeholder="2500.00"
                                   class="block w-full rounded-lg border-line bg-surface py-2 px-3 font-mono tabular-nums text-xs">
                        </div>
                        <div class="sm:col-span-3 flex items-end">
                            <button type="submit"
                                    class="w-full rounded-lg bg-ink hover:bg-slate-800 px-3 py-2 font-semibold text-paper transition">
                                Criar meta
                            </button>
                        </div>
                    </form>

                    @if(isset($savingsGoals) && $savingsGoals->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($savingsGoals as $goal)
                                @php
                                    $goalPct = $goal->progressPercent();
                                @endphp
                                <div class="rounded-xl border border-line bg-paper p-4 space-y-3 text-xs">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="min-w-0 space-y-0.5">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <h3 class="text-sm font-semibold text-ink">{{ $goal->name }}</h3>
                                                @if($goal->completed_at)
                                                    <span class="rounded bg-money-50 border border-money-200 px-2 py-0.5 text-[11px] font-semibold text-money-800">
                                                        Meta 100% atingida!
                                                    </span>
                                                @endif
                                            </div>
                                            @if($goal->product)
                                                <a href="{{ route('catalog.product', $goal->product->slug) }}" class="text-[11px] text-accent-700 hover:underline block truncate">
                                                    Produto vinculado: {{ $goal->product->name }} →
                                                </a>
                                            @endif
                                        </div>

                                        <div class="text-right shrink-0">
                                            <span class="font-mono font-bold tabular-nums text-ink">
                                                R$ {{ number_format((float) $goal->current_amount, 2, ',', '.') }}
                                            </span>
                                            <span class="text-slate-500 font-mono tabular-nums">
                                                / R$ {{ number_format((float) $goal->target_amount, 2, ',', '.') }} ({{ $goalPct }}%)
                                            </span>
                                        </div>
                                    </div>

                                    <div class="h-1.5 w-full rounded-full bg-surface border border-line overflow-hidden">
                                        <div class="h-full bg-money-600 transition-all" style="width: {{ $goalPct }}%"></div>
                                    </div>

                                    <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <form action="{{ route('goals.deposit', $goal) }}" method="POST" class="flex items-center gap-1.5">
                                                @csrf
                                                <label for="dep_goal_{{ $goal->id }}" class="sr-only">Guardar na meta {{ $goal->name }}</label>
                                                <input id="dep_goal_{{ $goal->id }}" type="number" name="amount" step="0.01" min="1.00" max="50000.00" inputmode="decimal"
                                                       required placeholder="R$ 100"
                                                       class="w-24 rounded-md border-line bg-surface py-1.5 px-2 font-mono tabular-nums text-xs">
                                                <button type="submit" class="rounded-md bg-money-600 hover:bg-money-700 px-3 py-1.5 font-semibold text-paper transition">
                                                    Guardar
                                                </button>
                                            </form>

                                            @if((float) $goal->current_amount > 0)
                                                <form action="{{ route('goals.withdraw', $goal) }}" method="POST" class="flex items-center gap-1.5">
                                                    @csrf
                                                    <label for="wd_goal_{{ $goal->id }}" class="sr-only">Resgatar da meta {{ $goal->name }}</label>
                                                    <input id="wd_goal_{{ $goal->id }}" type="number" name="amount" step="0.01" min="1.00" max="{{ (float) $goal->current_amount }}" inputmode="decimal"
                                                           value="{{ number_format((float) $goal->current_amount, 2, '.', '') }}" required
                                                           class="w-24 rounded-md border-line bg-surface py-1.5 px-2 font-mono tabular-nums text-xs">
                                                    <button type="submit" class="rounded-md border border-line bg-surface hover:border-ink px-3 py-1.5 font-medium text-ink transition">
                                                        Resgatar
                                                    </button>
                                                </form>
                                            @endif
                                        </div>

                                        <form action="{{ route('goals.destroy', $goal) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-medium text-danger-600 hover:underline">
                                                Excluir meta
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-slate-500 text-center py-2">
                            Crie sua primeira meta para separar saldo do cartão e planejar grandes compras sem comprometer seu dia a dia.
                        </p>
                    @endif
                </div>

                <div class="overflow-hidden rounded-xl border border-line bg-surface">
                    <div class="flex flex-col justify-between gap-3 border-b border-line p-5 sm:flex-row sm:items-center">
                        <div>
                            <h3 id="ledger-table-caption" class="text-sm font-semibold text-ink">Extrato de movimentações</h3>
                            <p class="text-xs text-slate-600 mt-0.5">Histórico imutável de recargas, compras, transferências, vales e cashback.</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <form action="{{ route('card.show') }}" method="GET" class="flex items-center">
                                <label for="tx_type_filter" class="sr-only">Filtrar por natureza</label>
                                <select id="tx_type_filter" name="tx_type" onchange="this.form.submit()"
                                        class="rounded-lg border-line bg-paper text-xs text-ink py-1.5 pl-2.5 pr-7 focus:border-ink focus:ring-ink">
                                    <option value="">Todas as naturezas</option>
                                    @foreach($typeLabels as $typeKey => [$typeName])
                                        <option value="{{ $typeKey }}" {{ ($selectedTxType ?? null) === $typeKey ? 'selected' : '' }}>
                                            {{ $typeName }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>

                            <a href="{{ route('card.statement.csv') }}"
                               class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-paper hover:border-ink px-3 py-1.5 text-xs font-medium text-ink transition">
                                <x-icon name="receipt" class="size-3.5 text-slate-500" />
                                <span>Exportar CSV</span>
                            </a>

                            <div class="inline-flex items-center rounded-lg bg-paper-dim p-1 text-xs font-medium">
                            <button @click="txFilter = 'todas'" type="button"
                                    :class="txFilter === 'todas' ? 'bg-surface text-ink shadow-xs' : 'text-slate-600 hover:text-ink'"
                                    class="rounded-md px-3 py-1.5 transition">
                                Todas ({{ $transactions->total() }})
                            </button>
                            <button @click="txFilter = 'entradas'" type="button"
                                    :class="txFilter === 'entradas' ? 'bg-money-600 text-paper shadow-xs' : 'text-slate-600 hover:text-ink'"
                                    class="rounded-md px-3 py-1.5 transition">
                                Entradas
                            </button>
                            <button @click="txFilter = 'saidas'" type="button"
                                    :class="txFilter === 'saidas' ? 'bg-danger-600 text-paper shadow-xs' : 'text-slate-600 hover:text-ink'"
                                    class="rounded-md px-3 py-1.5 transition">
                                Saídas
                            </button>
                            </div>
                        </div>
                    </div>

                    <div class="md:hidden divide-y divide-line">
                        @forelse($transactions as $transaction)
                            @php
                                $isCredit = (float) $transaction->amount >= 0;
                                [$typeLabel, $typeClasses] = $typeLabels[$transaction->type] ?? [$transaction->type, 'bg-paper text-slate-600 border-line'];
                            @endphp
                            <div x-show="txFilter === 'todas' || (txFilter === 'entradas' && {{ $isCredit ? 'true' : 'false' }}) || (txFilter === 'saidas' && {{ ! $isCredit ? 'true' : 'false' }})"
                                 class="p-4 space-y-1.5 text-xs">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-ink">{{ $transaction->description }}</span>
                                    <span class="font-mono tabular-nums font-semibold whitespace-nowrap {{ $isCredit ? 'text-money-700' : 'text-danger-700' }}">
                                        {{ $isCredit ? '+' : '-' }} R$ {{ number_format(abs((float) $transaction->amount), 2, ',', '.') }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-slate-500">
                                    <span>{{ $transaction->created_at->format('d/m/Y H:i') }} • {{ $typeLabel }}</span>
                                    <span class="font-mono tabular-nums">Saldo: R$ {{ number_format((float) $transaction->balance_after, 2, ',', '.') }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="p-6 text-center text-xs text-slate-500">Nenhuma movimentação registrada ainda.</p>
                        @endforelse
                    </div>

                    <div tabindex="0" role="region" aria-labelledby="ledger-table-caption" class="hidden md:block table-scroll-region">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr class="border-b border-line bg-paper text-xs font-medium text-slate-500">
                                    <th class="px-5 py-3">Data</th>
                                    <th class="px-5 py-3">Lançamento</th>
                                    <th class="px-5 py-3">Natureza</th>
                                    <th class="px-5 py-3 text-right">Valor</th>
                                    <th class="px-5 py-3 text-right">Saldo final</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line text-xs">
                                @forelse($transactions as $transaction)
                                    @php
                                        $isCredit = (float) $transaction->amount >= 0;
                                        [$typeLabel, $typeClasses] = $typeLabels[$transaction->type] ?? [$transaction->type, 'bg-paper text-slate-600 border-line'];
                                    @endphp
                                    <tr x-show="txFilter === 'todas' || (txFilter === 'entradas' && {{ $isCredit ? 'true' : 'false' }}) || (txFilter === 'saidas' && {{ ! $isCredit ? 'true' : 'false' }})"
                                        class="transition hover:bg-paper/60">
                                        <td class="px-5 py-3.5 text-slate-600 whitespace-nowrap">{{ $transaction->created_at->format('d/m/Y H:i') }}</td>
                                        <td class="px-5 py-3.5 font-medium text-ink">{{ $transaction->description }}</td>
                                        <td class="px-5 py-3.5">
                                            <span class="inline-flex rounded border px-2 py-0.5 text-xs font-medium {{ $typeClasses }}">{{ $typeLabel }}</span>
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-mono tabular-nums font-semibold whitespace-nowrap {{ $isCredit ? 'text-money-700' : 'text-danger-700' }}">
                                            {{ $isCredit ? '+' : '-' }} R$ {{ number_format(abs((float) $transaction->amount), 2, ',', '.') }}
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-mono tabular-nums text-slate-700 whitespace-nowrap">
                                            R$ {{ number_format((float) $transaction->balance_after, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-10 text-center text-slate-500">
                                            Nenhuma movimentação registrada ainda.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($transactions->hasPages())
                        <div class="p-4 border-t border-line">
                            {{ $transactions->links() }}
                        </div>
                    @endif
                </div>

                <div class="overflow-hidden rounded-xl border border-line bg-surface">
                    <div class="flex items-center justify-between border-b border-line p-5">
                        <div>
                            <h3 class="text-sm font-semibold text-ink">Histórico de recargas</h3>
                            <p class="text-xs text-slate-600 mt-0.5">Acompanhe suas cobranças PIX e comprovantes enviados.</p>
                        </div>
                        <span class="px-2.5 py-1 rounded-md bg-paper-dim text-xs font-medium text-slate-700">
                            {{ $recharges->total() }} {{ $recharges->total() === 1 ? 'registro' : 'registros' }}
                        </span>
                    </div>

                    <div class="md:hidden divide-y divide-line">
                        @forelse($recharges as $recharge)
                            <div class="p-4 space-y-1.5 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono tabular-nums font-semibold text-ink">#REC-{{ str_pad((string) $recharge->id, 5, '0', STR_PAD_LEFT) }}</span>
                                    <span class="font-mono tabular-nums font-semibold text-ink">
                                        R$ {{ number_format((float) ($recharge->approved_amount ?? $recharge->amount), 2, ',', '.') }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-slate-500">
                                    <span>{{ $recharge->created_at->format('d/m/Y H:i') }}</span>
                                    <span>{{ ucfirst($recharge->status) }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="p-6 text-center text-xs text-slate-500">Nenhuma recarga registrada até o momento.</p>
                        @endforelse
                    </div>

                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr class="border-b border-line bg-paper text-xs font-medium text-slate-500">
                                    <th class="px-5 py-3">Protocolo</th>
                                    <th class="px-5 py-3">Data</th>
                                    <th class="px-5 py-3">Valor</th>
                                    <th class="px-5 py-3">Origem / Arquivo</th>
                                    <th class="px-5 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line text-xs">
                                @forelse($recharges as $recharge)
                                    <tr class="transition hover:bg-paper/50">
                                        <td class="px-5 py-3.5 font-mono tabular-nums font-medium text-slate-700">
                                            #REC-{{ str_pad((string) $recharge->id, 5, '0', STR_PAD_LEFT) }}
                                        </td>
                                        <td class="px-5 py-3.5 text-slate-600">{{ $recharge->created_at->format('d/m/Y H:i') }}</td>
                                        <td class="px-5 py-3.5 font-mono tabular-nums font-semibold text-ink whitespace-nowrap">
                                            R$ {{ number_format((float) ($recharge->approved_amount ?? $recharge->amount), 2, ',', '.') }}
                                        </td>
                                        <td class="px-5 py-3.5">
                                            @if($recharge->receipt_path)
                                                <a href="{{ route('recharges.receipt', $recharge) }}" target="_blank" rel="noopener noreferrer"
                                                   class="inline-flex items-center gap-1 rounded-md border border-line bg-paper px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-paper-dim">
                                                    Ver comprovante
                                                </a>
                                            @else
                                                <span class="font-mono text-xs text-slate-600">{{ $recharge->txid ?? 'PIX' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5">
                                            @if ($recharge->status === 'aprovado')
                                                <span class="inline-flex items-center gap-1.5 rounded border border-money-200 bg-money-50 px-2 py-0.5 text-xs font-medium text-money-800">
                                                    <span class="size-1.5 rounded-full bg-money-500"></span> Aprovado
                                                </span>
                                            @elseif ($recharge->status === 'recusado')
                                                <span class="inline-flex items-center gap-1.5 rounded border border-danger-200 bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-800">
                                                    <span class="size-1.5 rounded-full bg-danger-500"></span> Recusado
                                                </span>
                                                @if ($recharge->admin_notes)
                                                    <span class="mt-1 block text-xs text-danger-600">Motivo: {{ $recharge->admin_notes }}</span>
                                                @endif
                                            @elseif ($recharge->status === 'expirado')
                                                <span class="inline-flex items-center gap-1.5 rounded border border-line bg-paper px-2 py-0.5 text-xs font-medium text-slate-600">
                                                    <span class="size-1.5 rounded-full bg-slate-400"></span> Expirado
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 rounded border border-accent-200 bg-accent-50 px-2 py-0.5 text-xs font-medium text-accent-900">
                                                    <span class="size-1.5 rounded-full bg-accent-500"></span> Pendente
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-10 text-center text-slate-500">
                                            Nenhuma recarga registrada até o momento.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($recharges->hasPages())
                        <div class="p-4 border-t border-line">
                            {{ $recharges->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
