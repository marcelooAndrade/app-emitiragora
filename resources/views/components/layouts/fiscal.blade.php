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
    // pior do que não mostrar.
    //
    // Agrupada por assunto: o que roda a viagem, o que mexe em dinheiro, o
    // que se lê para decidir e o que configura a empresa. Painel fica solto
    // no topo, sem rótulo: é o único destino que todo mundo sempre usa.
    // Configurações vai para baixo e nasce recolhida (ver partials.navegacao).
    // Desde 09/10/2026 nenhum rótulo se repete como grupo e como item, e cada
    // destino tem um ícone só seu (Heroicons, traço 1,5).
    $item = fn (string $rotulo, string $rota, string $permissao, string $icone): array => compact('rotulo', 'rota', 'permissao', 'icone');

    $secoes = collect([
        ['chave' => 'inicio', 'titulo' => null, 'itens' => [
            $item('Painel', 'dashboard', 'relatorio.ver', 'm2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25'),
        ]],
        ['chave' => 'operacao', 'titulo' => 'Operação', 'itens' => [
            $item('Viagens', 'viagens', 'transporte.ver', 'M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z'),
            $item('Clientes', 'destinatarios', 'pessoa.ver', 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z'),
            $item('Motoristas', 'motoristas', 'transporte.ver', 'M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z'),
            $item('Veículos', 'veiculos', 'transporte.ver', 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12'),
        ]],
        ['chave' => 'financeiro', 'titulo' => 'Financeiro', 'itens' => [
            $item('Visão geral', 'financeiro', 'financeiro.ver', 'M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605'),
            $item('Faturas', 'faturas', 'financeiro.ver', 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z'),
            $item('Contas a receber', 'contas-a-receber', 'financeiro.ver', 'm9 12.75 3 3m0 0 3-3m-3 3v-7.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'),
            $item('Contas a pagar', 'contas-a-pagar', 'financeiro.ver', 'm15 11.25-3-3m0 0-3 3m3-3v7.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'),
            $item('Contas bancárias', 'contas-bancarias', 'financeiro.ver', 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z'),
        ]],
        ['chave' => 'relatorios', 'titulo' => 'Relatórios', 'itens' => [
            $item('DRE', 'dre', 'financeiro.ver', 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z'),
            $item('Contabilidade', 'contabilidade', 'contador.exportar', 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z'),
        ]],
        ['chave' => 'configuracoes', 'titulo' => 'Configurações', 'itens' => [
            $item('Empresa', 'emitente', 'emitente.gerenciar', 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z'),
            $item('CT-e e MDF-e', 'transporte.configuracao', 'transporte.ver', 'M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75'),
            $item('Certificado', 'certificados', 'certificado.ver', 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z'),
            $item('Usuários', 'usuarios', 'usuario.gerenciar', 'M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z'),
            $item('Marca', 'marca', 'emitente.gerenciar', 'M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.75 3.75 0 0 1 3 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 0 0 3.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008Z'),
            $item('Centros de custo', 'centros-de-custo', 'financeiro.ver', 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3ZM6 6h.008v.008H6V6Z'),
        ]],
    ])->map(function (array $secao) use ($user): array {
        $secao['itens'] = collect($secao['itens'])
            ->filter(fn (array $destino): bool => (bool) $user?->can($destino['permissao']))
            ->map(fn (array $destino): array => $destino + ['ativo' => request()->routeIs($destino['rota'], $destino['rota'].'.*')])
            ->values();
        $secao['ativo'] = $secao['itens']->contains('ativo', true);

        return $secao;
    })->filter(fn (array $secao): bool => $secao['itens']->isNotEmpty())->keyBy('chave');

    $configuracoes = $secoes->pull('configuracoes');
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
        <x-ui.aviso-assinatura :tenant="$tenant" class="shrink-0" />

        <div class="flex min-h-0 flex-1">
        {{-- Sidebar, a partir de `md`. Abaixo disso a mesma navegação mora na
             gaveta `#menu-celular`, aberta pelo botão do topo. --}}
        <aside class="hidden min-h-0 w-64 shrink-0 flex-col bg-graphite-900 md:flex">
            @include('partials.navegacao-marca')
            @include('partials.navegacao', ['secoes' => $secoes, 'configuracoes' => $configuracoes])
        </aside>

        {{-- Gaveta do celular. `<dialog>` com `showModal()` dá de graça o fundo
             escurecido, o foco preso dentro e o Esc para fechar, sem Alpine.
             Clique no fundo fecha: o fundo pertence ao próprio `<dialog>`, e o
             conteúdo cobre a gaveta inteira, então só o fundo chega aqui como
             alvo. Os links recarregam a página, e a gaveta some com ela. --}}
        <dialog id="menu-celular" aria-label="Navegação"
                onclick="if (event.target === this) this.close()"
                class="m-0 h-dvh max-h-dvh w-72 max-w-[85vw] -translate-x-full bg-graphite-900 p-0 transition-[translate,overlay,display] duration-200 transition-discrete backdrop:bg-graphite-950/60 open:translate-x-0 starting:open:-translate-x-full motion-reduce:transition-none md:hidden">
            <div class="flex h-full w-full flex-col">
                <div class="flex shrink-0 items-center">
                    <div class="min-w-0 flex-1">@include('partials.navegacao-marca')</div>
                    <button type="button" aria-label="Fechar menu" onclick="this.closest('dialog').close()"
                            class="mr-3 flex size-9 items-center justify-center rounded-md text-graphite-400 outline-none hover:bg-white/[0.05] hover:text-white focus-visible:ring-2 focus-visible:ring-primary-500">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                @include('partials.navegacao', ['secoes' => $secoes, 'configuracoes' => $configuracoes])
            </div>
        </dialog>

        {{-- Lembra quais grupos a pessoa abriu ou fechou, por navegador. O grupo
             da tela atual (`data-ativo`) fica sempre aberto: esconder o item
             em que a pessoa está faria o menu mentir sobre onde ela está.
             Sem localStorage (janela privada, bloqueio), vale o padrão.

             Grava no clique do `<summary>`, não no evento `toggle`: o `toggle`
             dispara também quando o grupo já nasce aberto, e aí o Configurações
             aberto à força numa tela dele virava "escolha" e seguia aberto em
             todas as outras. O clique roda antes de o estado virar, por isso
             o valor gravado é o inverso do atual. Teclado (Enter, espaço) no
             `<summary>` também chega como clique. --}}
        <script>
            document.querySelectorAll('details[data-grupo]').forEach((grupo) => {
                const chave = 'menu:' + grupo.dataset.grupo;
                try {
                    const salvo = localStorage.getItem(chave);
                    if (salvo !== null && ! grupo.hasAttribute('data-ativo')) grupo.open = salvo === '1';
                } catch {}
                grupo.querySelector('summary').addEventListener('click', () => {
                    try { localStorage.setItem(chave, grupo.open ? '0' : '1'); } catch {}
                });
            });
        </script>

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            {{-- Topbar clara. Escura, ela empilhava uma terceira faixa sob o
                 banner de ambiente e a área de trabalho virava um poço. --}}
            <header class="flex h-14 shrink-0 flex-wrap items-center gap-3 border-b border-graphite-200 bg-white px-5">
                {{-- Abaixo de `md` a barra lateral some; este botão é a única
                     porta para a navegação no celular. --}}
                <button type="button" aria-label="Abrir menu" aria-controls="menu-celular" aria-haspopup="dialog"
                        onclick="document.getElementById('menu-celular').showModal()"
                        class="-ml-2 flex size-9 items-center justify-center rounded-md text-graphite-700 outline-none hover:bg-graphite-100 focus-visible:ring-2 focus-visible:ring-primary-500 md:hidden">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

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
