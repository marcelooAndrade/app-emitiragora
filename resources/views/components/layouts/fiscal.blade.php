@props(['title' => null])

@php
    $emitente = app(\App\Support\EmitenteAtual::class)->resolver();
    $tenant = app(\App\Support\TenantAtual::class)->obter();
    $user = auth()->user();
    $alcancaveis = $user ? app(\App\Support\EmitenteAtual::class)->alcancaveis() : collect();

    // Última consulta ao serviço de status, feita pelo comando agendado com o
    // certificado do emitente. A tela só lê: nunca sai para a SEFAZ daqui.
    $situacaoSefaz = $emitente ? app(\App\Services\Fiscal\MonitorSefaz::class)->situacao($emitente) : null;

    // A navegação reflete os módulos e a permissão de quem está olhando.
    // Item sem permissão não aparece: mostrar um caminho que leva a 403 é
    // pior do que não mostrar. `rota` nula é módulo ainda não construído.
    //
    // Agrupada por assunto, não por ritmo de uso: até 14/09 existia um grupo
    // "Operação" só, com 9 itens misturados (emissão, financeiro e estoque
    // juntos), e a lista tinha que ser lida inteira para achar qualquer
    // coisa. Separar por assunto (o que emite nota, o que mexe em dinheiro,
    // o que é cadastro e estoque) deixa no máximo 6 itens por grupo.
    //
    // Painel fica sem grupo, solto no topo: é o único destino que todo
    // mundo sempre usa, e não precisa de rótulo para ser encontrado.
    $grupos = ['', 'Fiscal', 'Transporte', 'Financeiro', 'Estoque e cadastros', 'Configuração'];

    $navegacao = collect([
        ['Painel', 'dashboard', 'relatorio.ver', '', 'M3 12h18M3 6h18M3 18h18'],

        ['Notas fiscais', 'notas', 'nota.ver', 'Fiscal', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['Notas de serviço', 'notas-servico', 'nfse.ver', 'Fiscal', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['Contabilidade', 'contabilidade', 'contador.exportar', 'Fiscal', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],

        ['Viagens', 'viagens', 'transporte.ver', 'Transporte', 'M8 17a2 2 0 11-4 0 2 2 0 014 0zm10 0a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0'],
        ['Veículos', 'veiculos', 'transporte.ver', 'Transporte', 'M12 21a9 9 0 100-18 9 9 0 000 18zm0-6a3 3 0 100-6 3 3 0 000 6zm0-6V3m0 18v-6m-3-3H3m18 0h-6'],
        ['Motoristas', 'motoristas', 'transporte.ver', 'Transporte', 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        ['Financeiro', 'financeiro', 'financeiro.ver', 'Financeiro', 'M3 3v16a2 2 0 002 2h16M7 15l3.5-4 3 3L20 7'],
        ['Faturas', 'faturas', 'financeiro.ver', 'Financeiro', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['Contas a pagar', 'contas-a-pagar', 'financeiro.ver', 'Financeiro', 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
        ['Contas a receber', 'contas-a-receber', 'financeiro.ver', 'Financeiro', 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['Contas bancárias', 'contas-bancarias', 'financeiro.ver', 'Financeiro', 'M3 10h18M5 6l7-3 7 3M5 10v9m4-9v9m6-9v9m4-9v9M3 19h18'],
        ['Centros de custo', 'centros-de-custo', 'financeiro.ver', 'Financeiro', 'M9 3v2m6-2v2M3 9h18M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z'],
        ['DRE', 'dre', 'financeiro.ver', 'Financeiro', 'M9 19v-6a2 2 0 012-2h2a2 2 0 012 2v6m-9 0h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],

        ['Estoque', 'estoque', 'estoque.ver', 'Estoque e cadastros', 'M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7M4 7h16M9 11h6'],
        ['Importação', 'importacao', 'importacao.ver', 'Estoque e cadastros', 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-8-4v8m0-8l-3 3m3-3l3 3M12 4v4'],
        ['Destinatários', 'destinatarios', 'pessoa.ver', 'Estoque e cadastros', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['Produtos', 'produtos', 'produto.ver', 'Estoque e cadastros', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],

        ['Regras fiscais', 'regras-fiscais', 'tributacao.gerenciar', 'Configuração', 'M9 12h6m-6 4h4m4-11V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V9l-4-4z'],
        ['Transporte', 'transporte.configuracao', 'transporte.ver', 'Configuração', 'M8 17a2 2 0 11-4 0 2 2 0 014 0zm10 0a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0'],
        ['Certificado', 'certificados', 'certificado.ver', 'Configuração', 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'],
        ['Emitente', 'emitente', 'emitente.gerenciar', 'Configuração', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
        ['Marca', 'marca', 'emitente.gerenciar', 'Configuração', 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343'],
        ['NFS-e', 'nfse', 'nfse.configurar', 'Configuração', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['Usuários', 'usuarios', 'usuario.gerenciar', 'Configuração', 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z'],

    ])->filter(fn (array $item): bool => $item[2] === null || $user?->can($item[2]))->groupBy(3);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-dvh bg-graphite-50 font-sans text-sm text-graphite-900 antialiased">

    {{-- Altura exata da tela, sem rolar: o corpo nunca rola, só `<main>` mais
         abaixo. Sem isso a barra lateral subia junto quando o conteúdo da
         página era mais alto que a tela, em vez de ficar travada no lugar. --}}
    <div class="flex h-dvh flex-col">
        @if ($emitente)
            <x-ui.env-banner :ambiente="$emitente->ambiente" class="shrink-0" />
        @endif

        <div class="flex min-h-0 flex-1">
        {{-- Sidebar. A barra vermelha de 3px no item ativo herda o motivo do
             hero do site, onde uma barra vertical vermelha corta a fachada. --}}
        <aside class="hidden min-h-0 w-60 shrink-0 flex-col bg-graphite-900 md:flex">
            <div class="flex h-14 shrink-0 items-center gap-2.5 border-b border-white/10 px-5">
                @if ($tenant?->logo_path)
                    <img src="{{ route('logo') }}" alt="{{ $tenant->nome }}" class="h-7 w-auto max-w-[9rem] object-contain">
                @else
                    {{-- A cor do texto vem da marca, não está cravada: sobre marca clara
                         escreve-se em grafite, sobre marca escura em branco. Ver
                         TemaMarca::textoSobre. --}}
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary-600 font-display text-xs font-bold text-on-primary">
                        {{ mb_strtoupper(mb_substr($tenant?->rotulo() ?? 'N', 0, 1)) }}
                    </span>
                    <span class="truncate font-display text-base font-bold uppercase tracking-wide text-white">
                        @if ($tenant?->rotulo())
                            {{ $tenant->rotulo() }}
                        @else
                            <span class="text-primary-600">EmitirAgora</span>
                        @endif
                    </span>
                @endif
            </div>

            <nav class="flex flex-1 flex-col gap-6 overflow-y-auto py-5" aria-label="Navegação principal">
                @foreach ($grupos as $grupo)
                    @continue (! isset($navegacao[$grupo]))
                    <div class="flex flex-col gap-0.5">
                        {{-- Painel usa a chave vazia, sem rótulo: é o único
                             destino que todo mundo sempre usa. --}}
                        @if ($grupo !== '')
                            <p class="etiqueta px-5 pb-2 text-graphite-500">{{ $grupo }}</p>
                        @endif

                        @foreach ($navegacao[$grupo] as [$rotulo, $rota, $permissao, $secao, $icone])
                            @php
                                $ativo = $rota && request()->routeIs($rota, $rota.".*");
                                $disponivel = $rota !== null;
                            @endphp
                            <a @if($disponivel) href="{{ route($rota) }}" @else aria-disabled="true" @endif
                               @class([
                                   'group relative flex items-center gap-3 py-2 pl-5 pr-4 text-[13px] transition-colors',
                                   'bg-white/10 font-medium text-white' => $ativo,
                                   'text-graphite-300 hover:bg-white/5 hover:text-white' => ! $ativo && $disponivel,
                                   'text-graphite-600 cursor-default' => ! $disponivel,
                               ])>
                                {{-- A barra vive num filho, e não numa borda do link, para
                                     que o fundo do item ativo comece na borda esquerda. --}}
                                <span aria-hidden="true"
                                      @class([
                                          'absolute inset-y-0 left-0 w-[3px] rounded-r-sm',
                                          'bg-primary-600' => $ativo,
                                          'bg-transparent' => ! $ativo,
                                      ])></span>
                                <svg class="size-[18px] shrink-0 {{ $ativo ? 'text-primary-600' : 'text-graphite-500 group-hover:text-graphite-300' }}"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone }}" />
                                </svg>
                                <span class="truncate">{{ $rotulo }}</span>
                                @unless ($disponivel)
                                    <span class="etiqueta ml-auto text-[9px] text-graphite-600">em breve</span>
                                @endunless
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>
        </aside>

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            {{-- Topbar clara. Escura, ela empilhava uma terceira faixa sob o
                 banner de ambiente e a área de trabalho virava um poço. --}}
            <header class="flex h-14 shrink-0 flex-wrap items-center gap-3 border-b border-graphite-200 bg-white px-5">
                @if ($emitente && $alcancaveis->count() > 1)
                    {{-- Mais de uma empresa alcançável: o selo vira seletor.
                         `<details>` no mesmo padrão sem JavaScript do menu do
                         usuário logo abaixo. --}}
                    <details class="group relative">
                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-md border border-graphite-200 bg-graphite-50 px-2.5 py-1.5 text-xs font-medium text-graphite-700 marker:content-none hover:bg-graphite-100">
                            <span class="size-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                            {{ $emitente->nome_fantasia ?: $emitente->razao_social }}
                            <svg class="size-3.5 text-graphite-400 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                            </svg>
                        </summary>

                        <label class="fixed inset-0 z-10 hidden cursor-default group-open:block" aria-hidden="true" onclick="this.closest('details').open = false"></label>

                        <div class="absolute left-0 z-20 mt-2 w-64 rounded-md border border-graphite-200 bg-white py-1 shadow-lg">
                            @foreach ($alcancaveis as $opcao)
                                <form method="POST" action="{{ route('emitente.escolher') }}">
                                    @csrf
                                    <input type="hidden" name="emitente_id" value="{{ $opcao->id }}" />
                                    <button type="submit"
                                            class="flex w-full flex-col items-start px-3 py-2 text-left text-sm hover:bg-graphite-50 {{ $opcao->is($emitente) ? 'bg-graphite-50 font-medium text-graphite-900' : 'text-graphite-700' }}">
                                        <span>{{ $opcao->nome_fantasia ?: $opcao->razao_social }}</span>
                                        <span class="text-xs text-graphite-500">{{ $opcao->tenant->rotulo() }}</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </details>
                @elseif ($emitente)
                    <span class="flex items-center gap-2 rounded-md border border-graphite-200 bg-graphite-50 px-2.5 py-1.5 text-xs font-medium text-graphite-700">
                        <span class="size-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                        {{ $emitente->nome_fantasia ?: $emitente->razao_social }}
                    </span>
                @else
                    <span class="text-xs text-graphite-500">Nenhum emitente vinculado</span>
                @endif

                {{-- O `ml-auto` fica no contêiner, não no indicador: sem emitente o
                     indicador não existe, e o menu do usuário precisa continuar
                     encostado à direita mesmo assim. --}}
                <div class="ml-auto flex items-center gap-3">
                {{-- Até 14/09 isto era texto fixo com bolinha verde: dizia "em
                     operação" sem consultar nada. Agora lê o MonitorSefaz, e o
                     title conta o cStat e a hora da consulta. --}}
                @if ($situacaoSefaz)
                    <span class="hidden items-center gap-2 text-xs text-graphite-500 sm:flex"
                          title="{{ $situacaoSefaz->descricao() }}">
                        <span class="size-1.5 rounded-full {{ $situacaoSefaz->estado->classeIndicador() }}" aria-hidden="true"></span>
                        {{ $situacaoSefaz->rotulo($emitente->uf) }}
                    </span>
                @endif

                {{-- `<details>` em vez de um dropdown com Alpine: o layout inteiro é
                     hand-rolled, sem `x-data` em lugar nenhum, e o `<details>` já é o
                     padrão do projeto para menu sem JavaScript (mesma solução do FAQ
                     da apresentação). Antes deste menu não existia jeito nenhum de
                     sair do sistema de dentro dele: o avatar era só decoração. --}}
                <details class="group relative">
                    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-md px-1.5 py-1 text-xs text-graphite-600 transition-colors marker:content-none hover:bg-graphite-50">
                        <span class="flex size-7 items-center justify-center rounded-full bg-graphite-800 text-[10px] font-semibold text-white">
                            {{ mb_strtoupper(mb_substr($user?->name ?? '?', 0, 2)) }}
                        </span>
                        <span class="hidden sm:inline">{{ $user?->name }}</span>
                        <svg class="size-3.5 text-graphite-400 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                        </svg>
                    </summary>

                    {{-- Fecha ao clicar fora: um `<label>` transparente do tamanho da
                         tela, atrás do painel, que também é `<summary>` de fechamento
                         por já estar dentro de outro `<details>` não seria simples só
                         com HTML, então o clique fora usa este truque de overlay. --}}
                    <label class="fixed inset-0 z-10 hidden cursor-default group-open:block" aria-hidden="true" onclick="this.closest('details').open = false"></label>

                    <div class="absolute right-0 z-20 mt-2 w-56 rounded-md border border-graphite-200 bg-white py-1 shadow-lg">
                        <div class="border-b border-graphite-100 px-3 py-2">
                            <p class="truncate text-sm font-medium text-graphite-900">{{ $user?->name }}</p>
                            <p class="truncate text-xs text-graphite-500">{{ $user?->email }}</p>
                        </div>
                        <a href="{{ route('profile.edit') }}" wire:navigate
                           class="block px-3 py-2 text-sm text-graphite-700 hover:bg-graphite-50">
                            Configurações da conta
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="block w-full px-3 py-2 text-left text-sm text-danger-600 hover:bg-danger-50">
                                Sair
                            </button>
                        </form>
                    </div>
                </details>
                </div>
            </header>

            {{-- O contêiner da página vive aqui, uma vez só. Cada view repetia
                 `mx-auto max-w-6xl`, e em monitor largo isso deixava faixas
                 vazias dos dois lados. Rola sozinho: é o único elemento com
                 rolagem própria em toda a tela, então a barra lateral e o
                 topo nunca se movem quando a página é mais alta que a tela. --}}
            <main class="min-w-0 flex-1 overflow-y-auto">
                {{-- `h-full`: sem efeito para a maioria das páginas, que só
                     têm a altura do próprio conteúdo. É o que permite a
                     Destinatários encaixar listagem e formulário na altura
                     cheia da tela, em vez de rolar a página inteira. --}}
                <div class="mx-auto h-full w-full max-w-[1800px] px-5 py-6 lg:px-8">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
    </div>

    {{-- O pixel de marketing não carrega no sistema fiscal: só entra aqui,
         uma única vez, na primeira tela vista logo depois de um cadastro,
         para o navegador confirmar a mesma conversão que o servidor já
         mandou via Conversions API (ver CreateNewUser). A sessão descarta o
         aviso na leitura, então uma nova visita ao painel nunca mais o
         repete. --}}
    @if ($eventoMeta = session('meta_pixel_evento'))
        @if (filled(config('integracao.meta.pixel_id')))
            @include('partials.pixel-meta', ['pixelId' => config('integracao.meta.pixel_id'), 'evento' => $eventoMeta])
        @endif
    @endif

    @fluxScripts
</body>
</html>
