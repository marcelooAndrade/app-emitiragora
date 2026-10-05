<x-layouts::auth title="Confira seu e-mail">
    <div class="flex flex-col gap-6 text-center">
        <x-auth-header title="Confira seu e-mail" description="Foi enviado o link de acesso para o seu e-mail." />

        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Mandamos para <strong class="text-zinc-900 dark:text-white">{{ $email }}</strong>.
            Abra o e-mail e clique em <strong class="text-zinc-900 dark:text-white">Criar minha senha</strong>
            pra entrar no sistema.
        </p>

        <p class="text-sm text-zinc-500">
            Não chegou em alguns minutos? Olhe no spam ou em Promoções, ou
            <flux:link :href="route('register', ['email' => $email])">peça de novo</flux:link>.
        </p>
    </div>
</x-layouts::auth>
