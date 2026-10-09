@props([])

{{-- Tabela sempre em contêiner com rolagem própria, para o corpo da página
     nunca rolar na horizontal. Cabeçalho escuro e em negrito, linhas altas
     com filete claro: o visual de 09/10/2026. Os seletores `[&_th]` vencem a
     cor que cada tela ainda escreve no próprio `th`. --}}
<div class="overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'w-full border-collapse text-sm [&_th]:text-graphite-800 [&_th]:font-semibold [&_th]:py-3.5 [&_td]:py-3.5 [&_tbody_tr]:border-t [&_tbody_tr]:border-graphite-100 [&_tbody_tr]:transition-colors [&_tbody_tr:hover]:bg-graphite-50/70']) }}>
        {{ $slot }}
    </table>
</div>
