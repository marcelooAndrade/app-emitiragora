{{--
    Os dois planos em cartões, com uma tabela de comparação abaixo.

    $icones, $chip e as classes de botão/cartão chegam por View::composer
    (registrado em AppServiceProvider para esta view, 'apresentacao' e
    'contadores'), então o componente funciona em qualquer uma das duas sem
    receber prop nenhuma. Tudo o que mostra vem de `config('planos')`, a
    fonte única de preço e recurso.
--}}
@php
    $planos = config('planos.planos');
    $acessoContadorEmTodos = config('planos.acessoDoContadorEmTodosOsPlanosPagos');

    // Recursos exibidos no cartão: os cadastrados no config, mais "Acesso do
    // contador" quando o plano se qualifica pela regra do toggle.
    $recursosDoCartao = function (array $plano) use ($acessoContadorEmTodos): array {
        $recursos = $plano['recursos'];

        if ($plano['slug'] === 'transporte' || $acessoContadorEmTodos) {
            $recursos[] = 'Acesso do contador';
        }

        return $recursos;
    };

    $formatarPreco = fn (int $centavos): string => 'R$'.number_format($centavos / 100, 0, ',', '.');

    // Todas as linhas que aparecem em algum plano, na ordem do config: o que
    // o plano menor tem vem primeiro, o que só o maior tem vem depois, e o
    // acesso do contador fecha a tabela.
    $linhasDaTabela = collect($planos)->flatMap(fn (array $plano): array => $plano['recursos'])
        ->unique()->push('Acesso do contador')->values()->all();

    $check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto h-4 w-4 text-primary-700" aria-hidden="true">'.$icones['check'].'</svg>';
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    <div class="mx-auto flex w-full max-w-4xl flex-col items-stretch justify-center gap-8 lg:flex-row">
        @foreach ($planos as $plano)
            <div class="relative flex w-full flex-col rounded-[2rem] border p-8 shadow-[0_10px_30px_-10px_rgba(14,27,31,0.08),inset_0_2px_0_rgba(255,255,255,1)] transition-all duration-500 hover:-translate-y-1 lg:w-1/2 {{ $plano['destaque'] ? 'border-primary-200 bg-gradient-to-b from-primary-50 to-white shadow-[0_15px_35px_-10px_rgba(228,87,46,0.15),inset_0_2px_0_rgba(255,255,255,1)]' : 'border-white bg-white/68' }}">
                @if ($plano['destaque'])
                    <span class="fonte-mono absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-b from-primary-600 to-primary-700 px-3 py-1 text-[0.65rem] tracking-[-0.04em] text-on-primary shadow-[0_5px_14px_rgba(228,87,46,0.28)]">RECOMENDADO PRA FROTA</span>
                @endif

                <p class="fonte-mono text-xs font-medium tracking-[-0.04em] {{ $plano['destaque'] ? 'text-primary-700' : 'text-graphite-500' }}">{{ $plano['nome'] }}</p>

                <p class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-normal tracking-tight text-graphite-900">{{ $formatarPreco($plano['precoCentavos']) }}</span>
                    <span class="text-sm font-light text-graphite-500">por mês</span>
                </p>

                <p class="mt-3 text-sm font-light leading-7 text-graphite-600">{{ $plano['descricao'] }}</p>

                <ul class="mt-7 flex-1 space-y-3 border-t pt-6 text-sm font-light text-graphite-700 {{ $plano['destaque'] ? 'border-primary-200/70' : 'border-graphite-200/70' }}">
                    @foreach ($recursosDoCartao($plano) as $item)
                        <li class="flex gap-2.5">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-1 h-4 w-4 shrink-0 {{ $plano['destaque'] ? 'text-primary-700' : 'text-graphite-400' }}" aria-hidden="true">{!! $icones['check'] !!}</svg>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>

                <a href="{{ route('register') }}" class="{{ $plano['destaque'] ? $botaoCheio : $botaoClaro }} mt-8 w-full py-3 text-sm">
                    Criar conta e testar grátis
                </a>
            </div>
        @endforeach
    </div>

    {{-- Tabela de comparação. Rola na horizontal no celular, sem quebrar a
         coluna de recursos. --}}
    <div class="mx-auto mt-10 max-w-4xl overflow-x-auto">
        <table class="w-full min-w-[28rem] border-separate border-spacing-0 text-sm">
            <thead>
                <tr>
                    <th class="w-1/2 p-4 text-left font-normal text-graphite-500"></th>
                    @foreach ($planos as $plano)
                        <th class="p-4 text-center font-medium {{ $plano['destaque'] ? 'text-primary-700' : 'text-graphite-900' }}">{{ $plano['nome'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($linhasDaTabela as $linha)
                    <tr class="border-t border-graphite-200/70">
                        <td class="p-4 text-left font-light text-graphite-600">{{ $linha }}</td>
                        @foreach ($planos as $plano)
                            <td class="p-4 text-center">
                                @if (in_array($linha, $recursosDoCartao($plano), true))
                                    {!! $check !!}
                                @else
                                    <span class="text-xs font-light text-graphite-300">não</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
