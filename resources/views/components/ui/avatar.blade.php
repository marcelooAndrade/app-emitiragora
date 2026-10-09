{{-- Iniciais num círculo no tom claro da marca, no lugar da foto que as
     listagens de referência mostram: o sistema não guarda foto de ninguém. --}}
@props(['nome'])

@php
    $partes = preg_split('/\s+/', trim((string) $nome)) ?: [];
    $iniciais = mb_strtoupper(mb_substr($partes[0] ?? '?', 0, 1).mb_substr(count($partes) > 1 ? end($partes) : '', 0, 1));
@endphp

<span {{ $attributes->merge(['class' => 'flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-600/10 text-xs font-bold text-primary-700']) }} aria-hidden="true">{{ $iniciais }}</span>
