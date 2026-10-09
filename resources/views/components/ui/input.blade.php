{{-- `min-w-0` é obrigatório: sem ele o campo carrega a largura intrínseca do
     atributo `size` como mínimo, e dentro de um grid isso vira piso que
     empurra a linha inteira para fora da tela no celular. --}}
@props(['type' => 'text', 'numeric' => false])

<input type="{{ $type }}"
    {{ $attributes->merge([
        'class' => 'min-h-11 min-w-0 rounded-md border border-graphite-200 bg-white px-4 text-sm text-graphite-900 '
            .'placeholder:text-graphite-400 hover:border-graphite-300 focus:border-primary-600 focus:outline-none focus:ring-4 '
            .'focus:ring-primary-600/15 disabled:bg-graphite-50 disabled:text-graphite-500 '
            .($numeric ? 'text-right [font-variant-numeric:tabular-nums]' : ''),
    ]) }}>
