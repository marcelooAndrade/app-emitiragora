{{-- Topo do cartão de listagem: busca em pílula à esquerda, filtros no meio
     e a ação de criar à direita, como nas listagens de referência. --}}
@props(['busca' => 'busca', 'placeholder' => 'Buscar'])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-3 px-6 py-5']) }}>
    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
        <div class="relative w-full sm:w-80">
            <svg class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-graphite-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            <x-ui.input type="search" wire:model.live.debounce.300ms="{{ $busca }}" :placeholder="$placeholder" aria-label="{{ $placeholder }}" class="w-full rounded-full! pl-10" />
        </div>
        {{ $filtros ?? '' }}
    </div>
    @isset($acao)
        <div class="flex shrink-0 items-center gap-2">{{ $acao }}</div>
    @endisset
</div>
