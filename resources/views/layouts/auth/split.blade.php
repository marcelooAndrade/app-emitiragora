<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    {{-- Fundo claro de propósito, igual ao layout simples: o `class="dark"`
         acima é do starter kit e está inerte, os componentes do Flux resolvem
         nas cores de modo claro. Só o painel da esquerda é escuro, e ele não
         tem componente do Flux dentro. --}}
    <body class="min-h-screen bg-white antialiased">
        @php
            // O tenant já vem resolvido pelo host: o ResolverTenant roda no
            // grupo web inteiro, inclusive nas rotas de visitante.
            $tenant = app(\App\Support\TenantAtual::class)->obter();

            // Marca do cliente na porta é benefício de plano. Hoje a condição
            // do plano é redundante (só o plano avançado tem domínio próprio, e
            // só por domínio próprio o host resolve um tenant), mas fica
            // explícita porque é regra de negócio.
            $marcaPropria = $tenant?->logo_path && $tenant->plano->permiteMarcaPropria();

            // O que o painel promete é o que o sistema tem hoje: nota, estoque,
            // financeiro e DRE. Nada de recurso que ainda não existe aqui.
            $destaques = ['NF-e e NFS-e', 'Financeiro com DRE', 'Acesso do contador'];
        @endphp

        <div class="grid min-h-svh lg:grid-cols-[1.1fr_1fr]">
            {{-- Painel da marca. No celular some, e a logo vai pro topo do
                 formulário. `self-start` + `sticky` seguram o painel no lugar
                 quando o formulário é mais alto que a tela (o cadastro). --}}
            <aside class="relative hidden overflow-hidden bg-graphite-950 text-white lg:sticky lg:top-0 lg:flex lg:h-svh lg:self-start lg:flex-col lg:justify-center lg:px-16 xl:px-24">
                <div aria-hidden="true" class="pointer-events-none absolute -left-40 -top-40 size-[44rem] rounded-full bg-[radial-gradient(circle,rgb(228_87_46/0.22),transparent_62%)]"></div>
                <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(255_255_255/0.05)_1px,transparent_1px)] [background-size:22px_22px]"></div>

                <div class="relative max-w-xl">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-3" wire:navigate>
                        {{-- A logo do cliente foi enviada pra viver na barra
                             lateral, que é escura: aqui ela fica no fundo certo. --}}
                        @if ($marcaPropria)
                            <img src="{{ route('logo') }}" alt="{{ $tenant->nome }}" class="h-10 w-auto max-w-[14rem] object-contain">
                        @else
                            <span class="flex size-11 items-center justify-center rounded-xl bg-white/5 ring-1 ring-white/10">
                                <x-marca-padrao class="size-7" />
                            </span>
                            <span class="font-display text-2xl font-bold tracking-tight">Emitir<span class="text-primary-600">Agora</span></span>
                        @endif
                    </a>

                    <p class="mt-20 text-xs font-bold uppercase tracking-[0.18em] text-primary-600">Gestão fiscal e financeira</p>
                    <h1 class="mt-5 font-display text-5xl font-bold leading-[1.05] tracking-tight xl:text-6xl">
                        Da nota emitida ao resultado do mês, tudo num só lugar.
                    </h1>
                    <p class="mt-6 max-w-lg text-base leading-7 text-graphite-300">
                        Notas, produtos, estoque, contas a pagar e a receber e DRE organizados para a rotina
                        real da sua empresa.
                    </p>

                    <ul class="mt-10 flex flex-wrap gap-x-6 gap-y-3 text-sm text-graphite-200">
                        @foreach ($destaques as $destaque)
                            <li class="flex items-center gap-2">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-primary-600" aria-hidden="true"><path d="M5 12l5 5L19 7" /></svg>
                                {{ $destaque }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </aside>

            {{-- Botões e links do Flux usam a cor de destaque, que no resto do
                 sistema é grafite. Na porta de entrada ela vira a cor da marca,
                 no tom 700: branco sobre o 600 daria contraste 3,68, abaixo do
                 mínimo; sobre o 700 dá 4,97. --}}
            <main class="flex flex-col items-center justify-center px-6 py-12 [--color-accent-content:var(--color-primary-700)] [--color-accent-foreground:#fff] [--color-accent:var(--color-primary-700)] sm:px-10">
                <div class="w-full max-w-md">
                    <a href="{{ route('home') }}" class="mb-10 inline-flex items-center gap-2.5 lg:hidden" wire:navigate>
                        @if ($marcaPropria)
                            {{-- Logo feita pro fundo escuro: no branco ela vai sobre uma placa. --}}
                            <span class="flex items-center rounded-md bg-graphite-900 px-4 py-3">
                                <img src="{{ route('logo') }}" alt="{{ $tenant->nome }}" class="h-8 w-auto max-w-[12rem] object-contain">
                            </span>
                        @else
                            <x-marca-padrao class="size-8" />
                            <span class="font-display text-xl font-bold tracking-tight text-graphite-900">Emitir<span class="text-primary-600">Agora</span></span>
                        @endif
                    </a>

                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
