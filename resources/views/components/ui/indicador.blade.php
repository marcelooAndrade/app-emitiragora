{{--
    Cartão de indicador do visual de 09/10/2026: ícone num quadrado de cor
    clara, o rótulo ao lado, o número grande embaixo e o detalhe por último.
    O tom do quadrado diz a natureza do número (marca, entrada, saída,
    alerta), não decora: um painel com quatro cores iguais não diz nada.

    $icone: o atributo `d` de um ícone Heroicons de traço 1,5.
    $tom: marca | sucesso | perigo | alerta | neutro.
    $valorClasse: cor do número, quando o número em si é o alerta.
--}}
@props(['rotulo', 'icone', 'tom' => 'marca', 'detalhe' => null, 'valorClasse' => 'text-graphite-900'])

@php
    $tons = [
        'marca' => 'bg-primary-600/10 text-primary-600',
        'sucesso' => 'bg-success-600/10 text-success-700',
        'perigo' => 'bg-danger-600/10 text-danger-700',
        'alerta' => 'bg-ember-500/15 text-ember-700',
        'neutro' => 'bg-graphite-900/[0.06] text-graphite-700',
    ];
@endphp

<x-ui.card {{ $attributes }}>
    <div class="flex items-center gap-3">
        <span class="flex size-11 shrink-0 items-center justify-center rounded-md {{ $tons[$tom] ?? $tons['marca'] }}" aria-hidden="true">
            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone }}" />
            </svg>
        </span>
        <p class="text-sm font-semibold text-graphite-700">{{ $rotulo }}</p>
    </div>
    <p class="num display-title mt-4 text-3xl {{ $valorClasse }}">{{ $slot }}</p>
    @if ($detalhe)
        <p class="mt-1 text-xs text-graphite-500">{{ $detalhe }}</p>
    @endif
</x-ui.card>
