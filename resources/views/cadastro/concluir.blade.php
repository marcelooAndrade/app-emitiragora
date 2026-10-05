<x-layouts::auth title="Concluir cadastro">
    {{-- Segunda metade do cadastro: só chega aqui quem abriu o link do
         e-mail. O e-mail aparece travado e não vai no formulário: quem manda
         é o convite, e o CreateNewUser lê o endereço dele. --}}

    <div class="flex flex-col gap-6">
        <x-auth-header title="E-mail confirmado" description="Agora crie sua senha e informe a sua empresa." />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <input type="hidden" name="convite" value="{{ $convite }}" />
            {{-- O e-mail não tem campo nesta tela, então o erro dele (já
                 cadastrado) e o do convite aparecem aqui em cima. --}}
            @foreach (['convite', 'email'] as $campo)
                @error($campo)
                    <p class="text-sm font-medium text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                @enderror
            @endforeach
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name', $cadastro->nome)"
                type="text"
                required
                autocomplete="name"
                :placeholder="__('Full name')"
            />

            <flux:input
                :label="__('Email address')"
                :value="$cadastro->email"
                type="email"
                disabled
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autofocus
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
                :value="old('telefone', $cadastro->telefone)"
                type="text"
                required
                inputmode="tel"
                autocomplete="tel"
                placeholder="(19) 99999-8888"
                description="É por onde a gente fala com você sobre a sua conta."
            />


            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

    </div>
</x-layouts::auth>
