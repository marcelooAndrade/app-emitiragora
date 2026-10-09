{{--
    Um destino do menu. A barra vermelha de 3px do item ativo é a assinatura
    da marca (Decisão 2 do design system); desde 09/10/2026 ela fica dentro
    da pílula, e não colada na borda da barra lateral.

    $item: ['rotulo', 'rota', 'icone', 'ativo'], montado no layout fiscal.
--}}
<a href="{{ route($item['rota']) }}"
   @if ($item['ativo']) aria-current="page" @endif
   @class([
       'group relative mx-3 flex items-center gap-3 rounded-md px-3 py-2 text-[13px] font-medium outline-none transition-colors focus-visible:ring-2 focus-visible:ring-primary-500',
       'bg-white/[0.08] text-white' => $item['ativo'],
       'text-graphite-300 hover:bg-white/[0.05] hover:text-white' => ! $item['ativo'],
   ])>
    @if ($item['ativo'])
        <span class="absolute left-0 top-1/2 h-4 w-[3px] -translate-y-1/2 rounded-sm bg-primary-600" aria-hidden="true"></span>
    @endif
    <svg @class([
            'size-[18px] shrink-0',
            'text-white' => $item['ativo'],
            'text-graphite-500 group-hover:text-graphite-300' => ! $item['ativo'],
         ])
         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icone'] }}" />
    </svg>
    <span class="truncate">{{ $item['rotulo'] }}</span>
</a>
