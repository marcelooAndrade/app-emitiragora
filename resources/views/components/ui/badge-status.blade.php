@props(['status'])

{{-- Pastilha em tom claro da cor do estado, sem ponto: o visual de
     09/10/2026 segue os painéis de referência, onde a cor de fundo já diz o
     estado e o texto confirma. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold whitespace-nowrap '.$status->classesBadge()]) }}>
    {{ $status->rotulo() }}
</span>
