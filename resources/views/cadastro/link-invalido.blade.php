<x-layouts::auth title="Link expirado">
    <div class="flex flex-col gap-6 text-center">
        <x-auth-header title="Este link não vale mais" description="Ele expirou, já foi usado ou um link mais novo foi enviado." />

        <flux:button variant="primary" :href="route('register')" class="w-full">Receber um link novo</flux:button>

        <div class="space-x-1 text-sm text-zinc-600 dark:text-zinc-400">
            <span>Já criou a senha?</span>
            <flux:link :href="route('login')">{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
