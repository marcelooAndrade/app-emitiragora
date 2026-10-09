{{-- Pastilha de situação sem enum por trás (ativo, inativo, padrão...). A
     de documento fiscal é a `x-ui.badge-status`, que lê a cor do enum. --}}
@props(['tom' => 'neutro'])

@php
    $tons = [
        'sucesso' => 'bg-success-50 text-success-700',
        'perigo' => 'bg-danger-50 text-danger-700',
        'alerta' => 'bg-ember-50 text-ember-800',
        'marca' => 'bg-primary-600/10 text-primary-700',
        'neutro' => 'bg-graphite-100 text-graphite-600',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold whitespace-nowrap '.($tons[$tom] ?? $tons['neutro'])]) }}>{{ $slot }}</span>
