@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
])

@php
    // A ação primária é grafite, nunca o vermelho da marca: primary-600 e
    // danger-600 têm contraste de apenas 1,43 entre si, e num emissor fiscal
    // "Transmitir" e "Cancelar CT-e" convivem na mesma tela.
    $variants = [
        'primary' => 'bg-graphite-900 text-white border border-graphite-900 shadow-sm hover:bg-graphite-700 hover:border-graphite-700',
        'secondary' => 'bg-white text-graphite-900 border border-graphite-300 shadow-sm hover:bg-graphite-50',
        'destructive' => 'bg-danger-600 text-white border border-danger-600 shadow-sm hover:bg-danger-700 hover:border-danger-700',
        'ghost' => 'bg-transparent text-graphite-600 border border-transparent hover:bg-graphite-100 hover:text-graphite-900',
    ];

    $sizes = [
        'sm' => 'min-h-8 px-3 text-xs',
        'md' => 'min-h-10 px-5 text-[13px]',
        'lg' => 'min-h-12 px-6 text-[13px]',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-center gap-2 rounded-md font-semibold uppercase tracking-[0.05em]',
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
