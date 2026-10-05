<x-layouts::auth :title="__('Register')">
    @php
        // O site manda nome, e-mail, telefone e as três respostas do perfil
        // pela URL quando o JavaScript dele não roda. `is_string` barra
        // `?nome[]=x`, que viraria array e quebraria o campo.
        $doSite = fn (string $chave): ?string => is_string($valor = request()->query($chave)) ? mb_substr($valor, 0, 254) : null;
    @endphp

    {{-- Primeira metade do cadastro: só o contato. A senha e a empresa vêm
         depois, pelo link que chega no e-mail. Ver CadastroController. --}}
    <div class="flex flex-col gap-6">
        <x-auth-header title="Crie sua conta grátis" description="Enviamos um link pro seu e-mail pra você criar a senha." />

        <form method="POST" action="{{ route('cadastro.iniciar') }}" class="flex flex-col gap-6">
            <flux:input
                name="nome"
                label="Seu nome"
                :value="old('nome', $doSite('nome'))"
                type="text"
                required
                autofocus
                autocomplete="name"
                placeholder="Nome completo"
            />

            <flux:input
                name="email"
                label="Seu e-mail"
                :value="old('email', $doSite('email'))"
                type="email"
                required
                autocomplete="email"
                placeholder="seu@email.com.br"
                description="O link de acesso chega neste e-mail."
            />

            <flux:input
                name="telefone"
                label="WhatsApp com DDD"
                :value="old('telefone', $doSite('telefone'))"
                type="text"
                required
                inputmode="tel"
                autocomplete="tel"
                placeholder="(19) 99999-8888"
            />

            @foreach (array_keys(\App\Support\PerfilDeCadastro::OPCOES) as $pergunta)
                @if (filled($valor = old($pergunta, $doSite($pergunta))))
                    <input type="hidden" name="{{ $pergunta }}" value="{{ $valor }}" />
                @endif
            @endforeach

            <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                Receber link de acesso
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
