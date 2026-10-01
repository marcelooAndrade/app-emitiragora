{{--
    Os três planos em cartões, com uma tabela de comparação abaixo.

    $icones, $chip e as classes de botão/cartão chegam por View::composer
    (registrado em AppServiceProvider para esta view, 'apresentacao' e
    'contadores'), então o componente funciona em qualquer uma das duas sem
    receber prop nenhuma. Tudo o que mostra vem de `config('planos')`, a
    fonte única de preço e recurso.
--}}
@php
    $planos = config('planos.planos');
    $acessoContadorEmTodos = config('planos.acessoDoContadorEmTodosOsPlanosPagos');
    $linkAssinatura = config('planos.pendencias.linkAssinatura');

    // Recursos exibidos no cartão: os cadastrados no config, mais "Acesso do
    // contador" quando o plano se qualifica pela regra do toggle.
    $recursosDoCartao = function (array $plano) use ($acessoContadorEmTodos): array {
        $recursos = $plano['recursos'];

        $temAcessoContador = $plano['slug'] !== 'gratis'
            && ($plano['slug'] === 'completo' || $acessoContadorEmTodos);

        if ($temAcessoContador) {
            $recursos[] = 'Acesso do contador';
        }

        return $recursos;
    };

    $formatarPreco = fn (int $centavos): string => $centavos === 0
        ? 'Grátis'
        : 'R$'.number_format($centavos / 100, 0, ',', '.');

    // Todas as linhas que aparecem em algum plano, na ordem em que entram na
    // tabela: o que todo plano tem primeiro, os recursos específicos depois.
    $linhasDaTabela = [
        'Notas por mês',
        'NF-e',
        'NFS-e',
        'Financeiro',
        'DRE',
        'Acesso do contador',
    ];
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    <div class="mx-auto flex w-full max-w-5xl flex-col items-stretch justify-center gap-8 lg:flex-row">
        @foreach ($planos as $plano)
            <div class="relative w-full rounded-[2rem] border p-8 shadow-[0_10px_30px_-10px_rgba(14,27,31,0.08),inset_0_2px_0_rgba(255,255,255,1)] transition-all duration-500 hover:-translate-y-1 lg:w-1/3 {{ $plano['destaque'] ? 'border-primary-200 bg-gradient-to-b from-primary-50 to-white shadow-[0_15px_35px_-10px_rgba(228,87,46,0.15),inset_0_2px_0_rgba(255,255,255,1)]' : 'border-white bg-white/68' }}">
                @if ($plano['destaque'])
                    <span class="fonte-mono absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-b from-primary-600 to-primary-700 px-3 py-1 text-[0.65rem] tracking-[-0.04em] text-on-primary shadow-[0_5px_14px_rgba(228,87,46,0.28)]">RECOMENDADO</span>
                @endif

                <p class="fonte-mono text-xs font-medium tracking-[-0.04em] {{ $plano['destaque'] ? 'text-primary-700' : 'text-graphite-500' }}">{{ $plano['nome'] }}</p>

                <p class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-normal tracking-tight text-graphite-900">{{ $formatarPreco($plano['precoCentavos']) }}</span>
                    @if ($plano['precoCentavos'] > 0)
                        <span class="text-sm font-light text-graphite-500">por mês</span>
                    @endif
                </p>

                <p class="mt-3 text-sm font-light leading-7 text-graphite-600">Até {{ $plano['notasPorMes'] }} notas por mês.</p>

                <ul class="mt-7 space-y-3 border-t pt-6 text-sm font-light text-graphite-700 {{ $plano['destaque'] ? 'border-primary-200/70' : 'border-graphite-200/70' }}">
                    @foreach ($recursosDoCartao($plano) as $item)
                        <li class="flex gap-2.5">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-1 h-4 w-4 shrink-0 {{ $plano['destaque'] ? 'text-primary-700' : 'text-graphite-400' }}" aria-hidden="true">{!! $icones['check'] !!}</svg>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>

                @if ($plano['precoCentavos'] === 0)
                    <a href="{{ route('register') }}" class="{{ $plano['destaque'] ? $botaoCheio : $botaoClaro }} mt-8 w-full py-3 text-sm">
                        Criar conta grátis
                    </a>
                @elseif (filled($linkAssinatura))
                    <a href="{{ $linkAssinatura }}" class="{{ $plano['destaque'] ? $botaoCheio : $botaoClaro }} mt-8 w-full py-3 text-sm">
                        Assinar o {{ $plano['nome'] }}
                    </a>
                @else
                    {{-- Sem checkout no projeto ainda: não dá pra mandar o
                         botão de um plano pago pro cadastro grátis como se
                         fosse assinatura. Fica desabilitado com o aviso. --}}
                    <span class="mt-8 flex w-full cursor-not-allowed items-center justify-center rounded-full border border-graphite-200 bg-graphite-50 px-5 py-3 text-xs font-normal text-graphite-400">
                        Assinatura em breve
                    </span>
                    <p class="mt-2 text-center text-[0.7rem] font-light text-graphite-400">Pendência de operação: ainda não há onde assinar este plano.</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Tabela de comparação. Rola na horizontal no celular, sem quebrar a
         coluna de recursos. --}}
    <div class="mx-auto mt-10 max-w-5xl overflow-x-auto">
        <table class="w-full min-w-[32rem] border-separate border-spacing-0 text-sm">
            <thead>
                <tr>
                    <th class="w-2/5 p-4 text-left font-normal text-graphite-500"></th>
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
                                @if ($linha === 'Notas por mês')
                                    <span class="num font-normal text-graphite-900">{{ $plano['notasPorMes'] }}</span>
                                @elseif ($linha === 'NF-e')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto h-4 w-4 text-primary-700" aria-hidden="true">{!! $icones['check'] !!}</svg>
                                @elseif ($linha === 'NFS-e')
                                    @if ($plano['nfse'])
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto h-4 w-4 text-primary-700" aria-hidden="true">{!! $icones['check'] !!}</svg>
                                    @else
                                        <span class="text-graphite-300">—</span>
                                    @endif
                                @elseif ($linha === 'Financeiro')
                                    <span class="font-light text-graphite-600">{{ match ($plano['financeiro']) {
                                        'completo' => 'Completo',
                                        'basico' => 'Básico',
                                        default => '—',
                                    } }}</span>
                                @elseif ($linha === 'DRE')
                                    @if ($plano['dre'])
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto h-4 w-4 text-primary-700" aria-hidden="true">{!! $icones['check'] !!}</svg>
                                    @else
                                        <span class="text-graphite-300">—</span>
                                    @endif
                                @elseif ($linha === 'Acesso do contador')
                                    @if (in_array('Acesso do contador', $recursosDoCartao($plano), true))
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto h-4 w-4 text-primary-700" aria-hidden="true">{!! $icones['check'] !!}</svg>
                                    @else
                                        <span class="text-graphite-300">—</span>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @php
        $cidades = config('planos.nfse.cidades');
    @endphp
    <p class="mx-auto mt-6 max-w-5xl text-center text-xs font-light text-graphite-500">
        @if (count($cidades) > 0)
            NFS-e disponível nas cidades: {{ implode(', ', $cidades) }}.
        @else
            NFS-e: lista de cidades em breve.
        @endif
    </p>
</div>
