<x-layouts::auth :title="__('Register')">
    @php
        // O modal do site manda nome, e-mail, telefone e as três respostas do
        // perfil pela URL. `is_string` barra `?nome[]=x`, que viraria array e
        // quebraria o campo. O `old()` vem antes: depois de um erro de
        // validação, vale o que a pessoa digitou, não o que veio do site.
        $doSite = fn (string $chave): ?string => is_string($valor = request()->query($chave)) ? mb_substr($valor, 0, 254) : null;
    @endphp

    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name', $doSite('nome'))"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email', $doSite('email'))"
                type="email"
                required
                autocomplete="email"
                placeholder="seu@email.com.br"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            {{-- Dados da empresa. Não são burocracia de formulário: sem
                 emitente não existe permissão neste sistema, e razão social,
                 CNPJ, inscrição estadual e regime são o mínimo que a SEFAZ
                 exige para um emitente existir. --}}
            <flux:separator text="Sua empresa" />

            <flux:input
                name="razao_social"
                label="Razão social"
                :value="old('razao_social')"
                type="text"
                required
                autocomplete="organization"
                placeholder="Como está no cartão CNPJ"
            />

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input
                    name="cnpj"
                    label="CNPJ"
                    :value="old('cnpj')"
                    type="text"
                    required
                    inputmode="text"
                    placeholder="00.000.000/0000-00"
                />

                <flux:input
                    name="inscricao_estadual"
                    label="Inscrição estadual"
                    :value="old('inscricao_estadual')"
                    type="text"
                    required
                    placeholder="Ou ISENTO"
                />
            </div>

            <flux:select name="crt" label="Regime tributário" required :value="old('crt')">
                <flux:select.option value="">Selecione</flux:select.option>
                <flux:select.option value="1">Simples Nacional</flux:select.option>
                <flux:select.option value="2">Simples Nacional, excesso de sublimite</flux:select.option>
                <flux:select.option value="3">Regime normal, presumido ou real</flux:select.option>
            </flux:select>

            <flux:input
                name="telefone"
                label="Telefone"
                :value="old('telefone', $doSite('telefone'))"
                type="text"
                required
                inputmode="tel"
                autocomplete="tel"
                placeholder="(19) 99999-8888"
                description="É por onde a gente fala com você sobre a sua conta."
            />

            {{-- Respostas do modal do site, só repassadas. Ver PerfilDeCadastro. --}}
            @foreach (array_keys(\App\Support\PerfilDeCadastro::OPCOES) as $pergunta)
                @if (filled($valor = old("perfil_{$pergunta}", $doSite($pergunta))))
                    <input type="hidden" name="perfil_{{ $pergunta }}" value="{{ $valor }}" />
                @endif
            @endforeach

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
