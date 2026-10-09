@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
])

@php
    // A ação primária é o petróleo da marca, nunca o vermelhão: primary-600 e
    // danger-600 têm contraste de apenas 1,77 entre si, e num emissor fiscal
    // "Transmitir" e "Cancelar CT-e" convivem na mesma tela. `suave` é a
    // ação de linha (ver, abrir), no tom claro da marca, e é onde o
    // vermelhão aparece sem disputar com o botão de perigo.
    $variants = [
        'primary' => 'bg-graphite-900 text-white hover:bg-graphite-700',
        'secondary' => 'bg-white text-graphite-800 ring-1 ring-inset ring-graphite-200 hover:bg-graphite-50 hover:ring-graphite-300',
        'suave' => 'bg-primary-50 text-primary-800 hover:bg-primary-100',
        'destructive' => 'bg-danger-600 text-white hover:bg-danger-700',
        'ghost' => 'bg-transparent text-graphite-600 hover:bg-graphite-100 hover:text-graphite-900',
    ];

    $sizes = [
        'sm' => 'min-h-9 px-4 text-[13px]',
        'md' => 'min-h-11 px-6 text-sm',
        'lg' => 'min-h-12 px-7 text-sm',
    ];

    // Pílula, escrita como se fala: o visual de 09/10/2026 tirou a caixa
    // alta espaçada dos botões.
    $classes = implode(' ', [
        'inline-flex items-center justify-center gap-2 rounded-full font-semibold whitespace-nowrap',
        'transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
        'focus-visible:outline-primary-600 disabled:opacity-50 disabled:pointer-events-none',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
