{{-- `padded=false` para quando o filho já traz o próprio recuo,
     como a tabela: recuo duplo faz o conteúdo flutuar dentro do card. --}}
@props(['title' => null, 'subtitle' => null, 'padded' => true])

{{-- Visual de 09/10/2026: cartão branco sem contorno, cantos largos e a
     sombra `cartao`, que descola do fundo cinza sem desenhar borda.

     `min-w-0` não é enfeite: item de grid nasce com `min-width: auto`,
     e sem isso a tabela larga estoura o card em vez de rolar dentro
     dele, pondo a página inteira para rolar na horizontal. --}}
<section {{ $attributes->merge(['class' => 'min-w-0 rounded-xl bg-white shadow-cartao']) }}>
    @if ($title || isset($actions))
        {{-- `shrink-0`: sem efeito quando o card é um bloco normal, e é o
             que mantém o título sempre visível quando quem chama vira o
             card num contêiner flex de altura cheia (ver Destinatários).
             O filete só aparece quando o conteúdo encosta nele (tabela). --}}
        <header @class([
            'flex shrink-0 flex-wrap items-center justify-between gap-3 px-6 pt-5',
            'pb-1' => $padded,
            'border-b border-graphite-100 pb-4' => ! $padded,
        ])>
            <div class="min-w-0">
                @if ($title)
                    <h2 class="display-title text-lg text-graphite-900">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-graphite-500">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    {{-- `min-h-0 flex-1`: idem, sem efeito fora de um contêiner flex. É o
         que deixa o conteúdo ocupar o resto da altura do card, para quem
         chama poder rolar só esta parte por dentro dela mesma. --}}
    <div @class(['p-6' => $padded, 'min-h-0 flex-1' => true])>{{ $slot }}</div>
</section>
