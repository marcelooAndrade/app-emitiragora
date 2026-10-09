@php
    $dados = $this->dados;
    $teto = $this->tetoDaSerie;

    // A escala é uma só para os dois sentidos. Se entrada e saída fossem
    // escaladas cada uma pelo próprio máximo, um mês de mil e um de cem mil
    // desenhariam a mesma barra, e o gráfico mentiria.
    // Aqui o float é geometria de pixel, não dinheiro: o centavo continua
    // inteiro em todo o caminho até a formatação.
    $proporcao = fn (int $centavos): float => $teto > 0 ? round($centavos / $teto * 100, 2) : 0.0;

    $semMovimento = collect($dados['meses'])
        ->every(fn (array $m): bool => $m['creditosCentavos'] === 0 && $m['debitosCentavos'] === 0);

    // Os valores que o balão de hover mostra já saem formatados daqui, para o
    // JavaScript não repetir a regra de moeda que o `Dinheiro` centraliza.
    $serie = collect($dados['meses'])->map(fn (array $m): array => [
        'rotulo' => $m['rotulo'],
        'entradas' => App\Support\Dinheiro::formatar($m['creditosCentavos']),
        'saidas' => App\Support\Dinheiro::formatar($m['debitosCentavos']),
        'resultado' => App\Support\Dinheiro::formatar($m['resultadoCentavos']),
        'positivo' => $m['resultadoCentavos'] >= 0,
    ])->values()->all();
@endphp

<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Financeiro"
        title="Painel"
        description="O que existe hoje, o que entrou e saiu no mês, e o que ainda vence." />

    {{-- O saldo é o número pelo qual esta tela é aberta, então ele não é um
         cartão igual aos outros: ocupa a linha inteira, ao lado da projeção
         que dele deriva. --}}
    <x-ui.card>
        <div class="grid gap-6 sm:grid-cols-2 sm:items-end">
            <div class="min-w-0">
                <p class="etiqueta text-graphite-500">Saldo em caixa</p>
                <p @class([
                    'num mt-1 text-4xl font-bold tracking-tight',
                    'text-graphite-900' => $dados['saldoCentavos'] >= 0,
                    'text-danger-700' => $dados['saldoCentavos'] < 0,
                ])>
                    <span class="text-xl font-semibold text-graphite-400">R$</span>
                    {{ App\Support\Dinheiro::formatar($dados['saldoCentavos']) }}
                </p>
                <p class="mt-1 text-xs text-graphite-500">
                    Saldo inicial das contas mais tudo o que já foi baixado.
                </p>
            </div>

            <div class="min-w-0 border-t border-graphite-200/70 pt-4 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-6">
                <p class="etiqueta text-graphite-500">Saldo projetado</p>
                <p @class([
                    'num mt-1 text-2xl font-bold',
                    'text-graphite-900' => $dados['saldoProjetadoCentavos'] >= 0,
                    'text-danger-700' => $dados['saldoProjetadoCentavos'] < 0,
                ])>
                    {{ App\Support\Dinheiro::formatar($dados['saldoProjetadoCentavos']) }}
                </p>
                <p class="mt-1 text-xs text-graphite-500">
                    Se tudo o que está em aberto for liquidado. Não é previsão de venda:
                    só entra o que já foi lançado.
                </p>
            </div>
        </div>
    </x-ui.card>

    {{-- Recebido, pago e resultado são números únicos. Número único é cartão,
         não gráfico: uma barra sozinha não compara com nada. --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.indicador rotulo="Recebido no mês" tom="sucesso" valorClasse="text-success-700"
            icone="m9 12.75 3 3m0 0 3-3m-3 3v-7.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z">
            {{ App\Support\Dinheiro::formatar($dados['recebidoNoMesCentavos']) }}
        </x-ui.indicador>

        <x-ui.indicador rotulo="Pago no mês" tom="perigo" valorClasse="text-danger-700"
            icone="m15 11.25-3-3m0 0-3 3m3-3v7.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z">
            {{ App\Support\Dinheiro::formatar($dados['pagoNoMesCentavos']) }}
        </x-ui.indicador>

        <x-ui.indicador rotulo="Resultado do mês" detalhe="Recebido menos pago."
            :tom="$dados['resultadoDoMesCentavos'] < 0 ? 'perigo' : 'marca'"
            :valorClasse="$dados['resultadoDoMesCentavos'] < 0 ? 'text-danger-700' : 'text-graphite-900'"
            icone="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z">
            {{ App\Support\Dinheiro::formatar($dados['resultadoDoMesCentavos']) }}
        </x-ui.indicador>
    </div>

    {{-- Em aberto e vencido. O vencido traz a quantidade junto porque
         "R$ 40.000 vencidos" em um título e em trinta pedem providências
         diferentes. --}}
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ([
            ['A receber', 'aReceberCentavos', 'receberVencidoCentavos', 'receberVencidoQuantidade', 'contas-a-receber'],
            ['A pagar', 'aPagarCentavos', 'pagarVencidoCentavos', 'pagarVencidoQuantidade', 'contas-a-pagar'],
        ] as [$rotulo, $chaveTotal, $chaveVencido, $chaveQuantidade, $rota])
            <x-ui.card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="etiqueta text-graphite-500">{{ $rotulo }}</p>
                        <p class="num mt-1 text-2xl font-bold text-graphite-900">
                            {{ App\Support\Dinheiro::formatar($dados[$chaveTotal]) }}
                        </p>
                    </div>
                    <a href="{{ route($rota) }}" wire:navigate
                        class="min-h-8 rounded-md px-2 py-1 text-xs font-semibold text-graphite-600 underline decoration-graphite-300 underline-offset-4 transition-colors hover:text-graphite-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                        Ver títulos
                    </a>
                </div>

                @if ($dados[$chaveQuantidade] > 0)
                    <p class="mt-3 flex flex-wrap items-center gap-2 border-t border-graphite-200/70 pt-3">
                        <span class="etiqueta bg-danger-100 px-2 py-1 text-danger-800">Vencido</span>
                        <span class="num text-sm font-semibold text-danger-700">
                            {{ App\Support\Dinheiro::formatar($dados[$chaveVencido]) }}
                        </span>
                        <span class="text-xs text-graphite-500">
                            em {{ $dados[$chaveQuantidade] }}
                            {{ $dados[$chaveQuantidade] === 1 ? 'título' : 'títulos' }}
                        </span>
                    </p>
                @else
                    <p class="mt-3 border-t border-graphite-200/70 pt-3 text-xs text-graphite-500">
                        Nada vencido.
                    </p>
                @endif
            </x-ui.card>
        @endforeach
    </div>

    {{-- Seis meses de entrada contra saída.

         Divergente, e não barras lado a lado, porque entrada e saída são
         polaridade: uma acrescenta e a outra tira. Com o zero no meio, a
         posição já diz o sentido, e a cor só reforça. Isso importa: o par
         verde e vermelho separa por ΔE 6,1 em deuteranopia, que a validação
         de paleta só admite quando existe uma segunda codificação. Aqui ela
         é a posição, mais a legenda, o rótulo no hover e a tabela abaixo. --}}
    <x-ui.card title="Entradas e saídas" subtitle="Seis meses, fechando no mês corrente. Escala em reais, o mesmo teto para os dois sentidos.">
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-4 text-xs text-graphite-600">
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-[2px] bg-success-600"></span>Entradas
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-[2px] bg-danger-600"></span>Saídas
                </span>
            </div>
        </x-slot:actions>

        <div x-data="{ ativo: null, serie: @js($serie) }" class="min-w-0">
            {{-- O recuo à direita abre a calha do eixo. Sem ela os rótulos de
                 escala cairiam por cima da barra do último mês. --}}
            <div class="relative pr-14 sm:pr-16" aria-hidden="true">
                <div class="relative">
                    {{-- Balão. Vive fora da grade das colunas para poder passar
                         por cima delas sem cortar. --}}
                    <template x-if="ativo !== null">
                        <div class="pointer-events-none absolute -top-2 z-30 w-40 rounded-md border border-graphite-200 bg-white p-2.5 shadow-lg"
                            :style="`left: clamp(0px, calc(${(ativo + 0.5) * (100 / serie.length)}% - 5rem), calc(100% - 10rem))`">
                            <p class="etiqueta text-graphite-500" x-text="serie[ativo].rotulo"></p>
                            <dl class="mt-1.5 grid gap-1 text-xs">
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="flex items-center gap-1.5 text-graphite-600">
                                        <span class="size-2 rounded-[2px] bg-success-600"></span>Entradas
                                    </dt>
                                    <dd class="num font-semibold text-graphite-900" x-text="serie[ativo].entradas"></dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="flex items-center gap-1.5 text-graphite-600">
                                        <span class="size-2 rounded-[2px] bg-danger-600"></span>Saídas
                                    </dt>
                                    <dd class="num font-semibold text-graphite-900" x-text="serie[ativo].saidas"></dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-3 border-t border-graphite-200/70 pt-1">
                                    <dt class="text-graphite-600">Resultado</dt>
                                    <dd class="num font-semibold"
                                        :class="serie[ativo].positivo ? 'text-graphite-900' : 'text-danger-700'"
                                        x-text="serie[ativo].resultado"></dd>
                                </div>
                            </dl>
                        </div>
                    </template>

                    {{-- Os extremos ficam atrás das barras, para não riscar a
                         barra que encosta neles. --}}
                    <div class="pointer-events-none absolute inset-x-0 top-0 z-0 h-52">
                        <div class="absolute inset-x-0 top-0 h-px bg-graphite-200"></div>
                        <div class="absolute inset-x-0 bottom-0 h-px bg-graphite-200"></div>
                    </div>

                    <div class="relative z-10 grid grid-cols-6 gap-1">
                        @foreach ($dados['meses'] as $i => $mes)
                            {{-- A coluna inteira é o alvo, e não a barra: mirar num
                                 traço de 3px com o mouse é trabalho. --}}
                            <div tabindex="0"
                                @mouseenter="ativo = {{ $i }}" @mouseleave="ativo = null"
                                @focus="ativo = {{ $i }}" @blur="ativo = null"
                                :class="ativo === {{ $i }} ? 'bg-graphite-50' : ''"
                                class="min-w-0 rounded-md transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">

                                {{-- As duas metades têm a mesma altura fixa, e é
                                     por isso que a linha do zero, desenhada na
                                     metade da altura total, cai exatamente entre
                                     elas em qualquer largura de tela. --}}
                                <div class="flex h-26 items-end justify-center">
                                    @if ($mes['creditosCentavos'] > 0)
                                        <div class="w-3/5 max-w-9 min-w-1.5 rounded-t-[4px] bg-success-600"
                                            style="height: max(3px, {{ $proporcao($mes['creditosCentavos']) }}%)"></div>
                                    @endif
                                </div>

                                <div class="flex h-26 items-start justify-center">
                                    @if ($mes['debitosCentavos'] > 0)
                                        <div class="w-3/5 max-w-9 min-w-1.5 rounded-b-[4px] bg-danger-600"
                                            style="height: max(3px, {{ $proporcao($mes['debitosCentavos']) }}%)"></div>
                                    @endif
                                </div>

                                <p class="mt-2 text-center text-xs text-graphite-500">{{ $mes['rotulo'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    {{-- A linha do zero passa por cima das barras. É ela que dá o
                         sentido ao gráfico, então atravessa inteira em vez de
                         virar seis tracinhos com buraco entre as colunas, e o
                         par de pixels que ela come de cada lado é o respiro
                         entre os dois preenchimentos. --}}
                    <div class="pointer-events-none absolute inset-x-0 top-0 z-20 h-52">
                        <div class="absolute inset-x-0 top-1/2 h-0.5 -translate-y-1/2 bg-graphite-400"></div>
                    </div>

                    @if ($semMovimento)
                        <p class="absolute inset-x-0 top-26 z-20 -translate-y-1/2 text-center text-sm text-graphite-400">
                            Nenhum movimento nestes seis meses.
                        </p>
                    @endif
                </div>

                {{-- O eixo. Sem ele não dá para saber se a maior barra é dez mil
                     ou dez milhões, e o gráfico vira desenho. Os centavos saem:
                     escala não se lê no centavo. --}}
                <div class="pointer-events-none absolute top-0 right-0 h-52 w-14 pl-2 sm:w-16">
                    <span class="num absolute top-0 right-0 -translate-y-1/2 text-[11px] text-graphite-400">
                        {{ number_format(intdiv($teto, 100), 0, ',', '.') }}
                    </span>
                    <span class="num absolute top-1/2 right-0 -translate-y-1/2 text-[11px] font-semibold text-graphite-500">0</span>
                    <span class="num absolute right-0 bottom-0 translate-y-1/2 text-[11px] text-graphite-400">
                        {{ number_format(intdiv($teto, 100), 0, ',', '.') }}
                    </span>
                </div>
            </div>

            {{-- A tabela não é redundância: é como quem usa leitor de tela, ou
                 quem precisa do valor exato, chega ao mesmo dado. --}}
            <details class="mt-5 border-t border-graphite-200/70 pt-4">
                <summary class="cursor-pointer text-xs font-semibold text-graphite-600 hover:text-graphite-900">
                    Ver os números
                </summary>
                <div class="mt-3">
                    <x-ui.table>
                        <thead>
                            <tr class="border-b border-graphite-200">
                                <th class="etiqueta px-2 py-2 text-left text-graphite-500">Mês</th>
                                <th class="etiqueta px-2 py-2 text-right text-graphite-500">Entradas</th>
                                <th class="etiqueta px-2 py-2 text-right text-graphite-500">Saídas</th>
                                <th class="etiqueta px-2 py-2 text-right text-graphite-500">Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dados['meses'] as $mes)
                                <tr class="border-b border-graphite-100">
                                    <td class="px-2 py-2 text-graphite-900">{{ $mes['rotulo'] }}</td>
                                    <td class="num px-2 py-2 text-right text-graphite-700">
                                        {{ App\Support\Dinheiro::formatar($mes['creditosCentavos']) }}
                                    </td>
                                    <td class="num px-2 py-2 text-right text-graphite-700">
                                        {{ App\Support\Dinheiro::formatar($mes['debitosCentavos']) }}
                                    </td>
                                    <td @class([
                                        'num px-2 py-2 text-right font-semibold',
                                        'text-graphite-900' => $mes['resultadoCentavos'] >= 0,
                                        'text-danger-700' => $mes['resultadoCentavos'] < 0,
                                    ])>
                                        {{ App\Support\Dinheiro::formatar($mes['resultadoCentavos']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
            </details>
        </div>
    </x-ui.card>

    {{-- O tamanho da operação e os quatro caminhos mais batidos. --}}
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]">
        <x-ui.card title="Base ativa" subtitle="O tamanho da operação hoje.">
            <dl class="grid gap-3">
                <a href="{{ route('destinatarios') }}" wire:navigate class="flex items-baseline justify-between gap-3 hover:text-primary-700">
                    <dt class="text-sm text-graphite-700">Clientes</dt>
                    <dd class="num text-2xl font-bold text-graphite-900">{{ $dados['clientes'] }}</dd>
                </a>
                <a href="{{ route('contas-a-receber') }}" wire:navigate class="flex items-baseline justify-between gap-3 hover:text-primary-700">
                    <dt class="text-sm text-graphite-700">Faturas ativas</dt>
                    <dd class="num text-2xl font-bold text-graphite-900">{{ $dados['faturasAtivas'] }}</dd>
                </a>
            </dl>
        </x-ui.card>

        <x-ui.card title="Atalhos">
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ([
                    ['Movimentar caixa', 'Registrar entrada ou saída', 'contas-bancarias'],
                    ['Emitir fatura', 'Criar cobrança para cliente', 'contas-a-receber'],
                    ['Nova conta a pagar', 'Registrar um compromisso', 'contas-a-pagar'],
                    ['Analisar DRE', 'Ver receitas e despesas', 'dre'],
                ] as [$titulo, $subtitulo, $rota])
                    <a href="{{ route($rota) }}" wire:navigate
                        class="flex items-center justify-between gap-3 rounded-md border border-graphite-200 px-4 py-3 transition-colors hover:border-graphite-400 hover:bg-graphite-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-graphite-900">{{ $titulo }}</span>
                            <span class="block truncate text-xs text-graphite-500">{{ $subtitulo }}</span>
                        </span>
                        <span aria-hidden="true" class="shrink-0 text-graphite-400">→</span>
                    </a>
                @endforeach
            </div>
        </x-ui.card>
    </div>

    {{-- Sete linhas, vencido primeiro. A lista completa tem tela própria: esta
         existe para caber na tela sem rolagem e dizer o que fazer hoje. --}}
    <x-ui.card title="Próximos compromissos" subtitle="Vencido primeiro, depois por data.">
        @if ($dados['compromissos'] === [])
            <x-ui.empty-state
                title="Nada a vencer"
                description="Quando houver título em aberto, ele aparece aqui antes de vencer." />
        @else
            <ul class="grid gap-px bg-graphite-200/70">
                @foreach ($dados['compromissos'] as $item)
                    <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 bg-white py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            {{-- Receber e pagar são estados, então vêm com
                                 rótulo escrito, nunca só pela cor. --}}
                            <span @class([
                                'etiqueta shrink-0 px-2 py-1',
                                'bg-success-100 text-success-800' => $item['tipo'] === 'receber',
                                'bg-danger-100 text-danger-800' => $item['tipo'] === 'pagar',
                            ])>
                                {{ $item['tipo'] === 'receber' ? 'Receber' : 'Pagar' }}
                            </span>

                            <div class="min-w-0">
                                <p class="truncate text-graphite-900">{{ $item['titulo'] }}</p>
                                @if (filled($item['subtitulo']))
                                    <p class="truncate text-xs text-graphite-500">{{ $item['subtitulo'] }}</p>
                                @endif
                            </div>
                        </div>

                        {{-- O selo vem antes da data, e a data tem largura fixa.
                             Com o selo depois, ele empurrava a data de cada linha
                             para um lugar diferente e a coluna ficava irregular. --}}
                        <div class="flex shrink-0 items-baseline gap-3">
                            @if ($item['vencido'])
                                <span class="etiqueta bg-danger-100 px-1.5 py-0.5 text-danger-800">Vencido</span>
                            @endif
                            <span @class([
                                'num w-20 text-right text-xs whitespace-nowrap',
                                'font-semibold text-danger-700' => $item['vencido'],
                                'text-graphite-500' => ! $item['vencido'],
                            ])>
                                {{ \Illuminate\Support\Carbon::parse($item['vencimento'])->format('d/m/Y') }}
                            </span>
                            <span class="num w-24 text-right font-semibold text-graphite-900">
                                {{ App\Support\Dinheiro::formatar($item['valorCentavos']) }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>
