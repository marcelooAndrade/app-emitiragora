{{--
    A navegação do layout fiscal, usada duas vezes: na barra lateral (a partir
    de `md`) e na gaveta do celular. Os dois lugares recebem a mesma lista,
    montada e filtrada por permissão uma vez só no layout.

    Grupos recolhem com `<details>`, sem Alpine: o layout é todo em HTML
    nativo, como o menu do usuário. Configurações fica fixa embaixo e nasce
    recolhida, porque é visitada uma vez na vida e não pode pesar igual a
    Viagens; abre sozinha quando a tela atual é uma das dela. O que a pessoa
    abre ou fecha é lembrado pelo script do layout (`data-grupo`).

    $secoes: grupos do meio, em ordem; o de `titulo` nulo é o Painel, solto.
    $configuracoes: o grupo de baixo, ou nulo quando nenhum item é permitido.
--}}
@php
    $chevron = 'M19.5 8.25l-7.5 7.5-7.5-7.5';
@endphp

<nav class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto pb-4 pt-2" aria-label="Navegação principal">
    @foreach ($secoes as $secao)
        @if ($secao['titulo'] === null)
            <div class="flex flex-col gap-0.5">
                @foreach ($secao['itens'] as $item)
                    @include('partials.navegacao-item', ['item' => $item])
                @endforeach
            </div>
        @else
            <details data-grupo="{{ $secao['chave'] }}" @if ($secao['ativo']) data-ativo @endif open class="group/grupo">
                <summary class="mx-3 flex cursor-pointer list-none items-center justify-between rounded-md px-3 py-1 text-[11px] font-semibold text-graphite-400 outline-none transition-colors hover:text-graphite-200 focus-visible:ring-2 focus-visible:ring-primary-500 [&::-webkit-details-marker]:hidden">
                    {{ $secao['titulo'] }}
                    <svg class="size-3 -rotate-90 transition-transform group-open/grupo:rotate-0 motion-reduce:transition-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevron }}" />
                    </svg>
                </summary>
                <div class="mt-1 flex flex-col gap-0.5">
                    @foreach ($secao['itens'] as $item)
                        @include('partials.navegacao-item', ['item' => $item])
                    @endforeach
                </div>
            </details>
        @endif
    @endforeach
</nav>

@if ($configuracoes)
    <div class="shrink-0 border-t border-white/10 py-3">
        <details data-grupo="configuracoes" @if ($configuracoes['ativo']) data-ativo open @endif class="group/grupo">
            <summary class="mx-3 flex cursor-pointer list-none items-center gap-3 rounded-md px-3 py-2 text-[13px] font-medium text-graphite-300 outline-none transition-colors hover:bg-white/[0.05] hover:text-white focus-visible:ring-2 focus-visible:ring-primary-500 [&::-webkit-details-marker]:hidden">
                <svg class="size-[18px] shrink-0 text-graphite-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <span class="flex-1">Configurações</span>
                <svg class="size-3 text-graphite-500 -rotate-90 transition-transform group-open/grupo:rotate-0 motion-reduce:transition-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevron }}" />
                </svg>
            </summary>
            <div class="mt-1 flex flex-col gap-0.5">
                @foreach ($configuracoes['itens'] as $item)
                    @include('partials.navegacao-item', ['item' => $item])
                @endforeach
            </div>
        </details>
    </div>
@endif
