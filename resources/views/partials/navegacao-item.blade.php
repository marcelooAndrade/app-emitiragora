{{--
    Um destino do menu. Visual de 09/10/2026, sobre barra lateral clara: o
    item ativo é uma pílula no tom mais claro da marca, com o ícone na cor
    da marca. Em tenant com marca própria, as duas cores vêm do TemaMarca.

    $item: ['rotulo', 'rota', 'icone', 'ativo'], montado no layout fiscal.
--}}
<a href="{{ route($item['rota']) }}"
   @if ($item['ativo']) aria-current="page" @endif
   @class([
       'group relative mx-3 flex items-center gap-3 rounded-md px-3.5 py-2.5 text-sm font-medium outline-none transition-colors focus-visible:ring-2 focus-visible:ring-primary-500',
       'bg-primary-600/10 font-semibold text-graphite-900' => $item['ativo'],
       'text-graphite-600 hover:bg-graphite-50 hover:text-graphite-900' => ! $item['ativo'],
   ])>
    <svg @class([
            'size-5 shrink-0',
            'text-primary-600' => $item['ativo'],
            'text-graphite-400 group-hover:text-graphite-600' => ! $item['ativo'],
         ])
         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icone'] }}" />
    </svg>
    <span class="truncate">{{ $item['rotulo'] }}</span>
</a>
