<x-guest-layout>
    <div class="pb-5 border-b border-line">
        <x-eyebrow>Cadastro Empresarial</x-eyebrow>
        <h2 class="font-serif text-2xl text-ink mt-1">Cadastre sua empresa na NexoSupri</h2>
        <p class="text-xs text-slate-600 mt-1">A carteira de créditos exclusiva (Modelo A) da sua empresa é criada automaticamente após o cadastro.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4 pt-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="company_trade_name" class="block text-xs font-medium text-slate-700 mb-1">Nome Fantasia da Empresa</label>
                <input id="company_trade_name" type="text" name="company_trade_name" value="{{ old('company_trade_name') }}"
                       placeholder="Ex: Alfa Soluções"
                       class="block w-full rounded-lg border-line text-sm py-2.5 focus:border-ink focus:ring-ink">
                <x-input-error :messages="$errors->get('company_trade_name')" class="mt-1.5" />
            </div>

            <div>
                <label for="cnpj" class="block text-xs font-medium text-slate-700 mb-1">CNPJ</label>
                <input id="cnpj" type="text" name="cnpj" value="{{ old('cnpj') }}"
                       placeholder="00.000.000/0001-00"
                       class="block w-full rounded-lg border-line font-mono text-sm py-2.5 focus:border-ink focus:ring-ink">
                <x-input-error :messages="$errors->get('cnpj')" class="mt-1.5" />
            </div>
        </div>

        <div>
            <label for="company_legal_name" class="block text-xs font-medium text-slate-700 mb-1">Razão Social (opcional)</label>
            <input id="company_legal_name" type="text" name="company_legal_name" value="{{ old('company_legal_name') }}"
                   placeholder="Ex: Alfa Soluções Corporativas Ltda"
                   class="block w-full rounded-lg border-line text-sm py-2.5 focus:border-ink focus:ring-ink">
            <x-input-error :messages="$errors->get('company_legal_name')" class="mt-1.5" />
        </div>

        <div>
            <label for="name" class="block text-xs font-medium text-slate-700 mb-1">Nome do Responsável (Gestor)</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                   placeholder="Ex: Mariana Costa"
                   class="block w-full rounded-lg border-line text-sm py-2.5 focus:border-ink focus:ring-ink">
            <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="email" class="block text-xs font-medium text-slate-700 mb-1">E-mail corporativo</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                       placeholder="compras@empresa.com.br"
                       class="block w-full rounded-lg border-line text-sm py-2.5 focus:border-ink focus:ring-ink">
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>

            <div>
                <label for="phone" class="block text-xs font-medium text-slate-700 mb-1">Telefone / WhatsApp</label>
                <input id="phone" type="text" name="phone" value="{{ old('phone') }}"
                       placeholder="(11) 99999-9999"
                       class="block w-full rounded-lg border-line text-sm py-2.5 focus:border-ink focus:ring-ink">
                <x-input-error :messages="$errors->get('phone')" class="mt-1.5" />
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="password" class="block text-xs font-medium text-slate-700 mb-1">Senha</label>
                <input id="password" type="password" name="password" required autocomplete="new-password"
                       placeholder="Mín. 8 caracteres"
                       class="block w-full rounded-lg border-line text-sm py-2.5 focus:border-ink focus:ring-ink">
                <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-medium text-slate-700 mb-1">Confirmar senha</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                       placeholder="Repita a senha"
                       class="block w-full rounded-lg border-line text-sm py-2.5 focus:border-ink focus:ring-ink">
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5" />
            </div>
        </div>

        <button type="submit"
                class="w-full bg-ink hover:bg-slate-800 text-paper font-semibold text-xs py-3 px-4 rounded-lg transition flex items-center justify-center gap-2">
            <span>Cadastrar empresa e abrir carteira</span>
            <x-icon name="arrow-right" class="size-4" />
        </button>

        <div class="text-center pt-2 border-t border-line">
            <a class="text-xs font-medium text-slate-600 hover:text-ink underline" href="{{ route('login') }}">
                Já possui acesso? Entrar aqui
            </a>
        </div>
    </form>
</x-guest-layout>
