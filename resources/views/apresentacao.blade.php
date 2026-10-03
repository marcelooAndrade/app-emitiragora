{{--
    Apresentação do produto, servida no domínio nu pelo RaizController.

    Esta página é um port do template de referência espelhado em
    `templates/aura-assistant-aura/`: dele vêm a arquitetura de seções, a
    tipografia (Inter nos pesos 300 a 600, nunca bold, e JetBrains Mono nos
    rótulos miúdos), a escala de tamanho, o espaçamento, os raios largos, as
    sombras com brilho interno e o fundo fixo de manchas sob trama de pontos.
    Da Venda Redonda vêm apenas a logo e as cores, que continuam saindo das
    rampas do `TemaMarca`: o azul de lá virou vermelhão, o azul-marinho virou
    grafite. O conteúdo é nosso, encaixado nos mesmos lugares.

    A versão anterior, em Manrope e na escala do sistema, está preservada em
    `apresentacao-anterior.blade.php` e não é servida por rota nenhuma.

    O revelar ao rolar segue sendo progressive enhancement: `data-revelar` é
    observado por `resources/js/app.js`, e sem JavaScript ou com
    `prefers-reduced-motion` o conteúdo já nasce visível.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    {{-- A marca vai cravada, não vem de `APP_NAME`. E sem `title`, senão o
         partial concatena e sai "Venda Redonda - Venda Redonda". --}}
    @include('partials.head', ['marca' => 'EmitirAgora'])

    {{-- As duas famílias do template. O sistema segue em Manrope; só esta
         página usa Inter e JetBrains Mono. --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500;600&display=swap">

    @php
        $descricao = 'Sistema fiscal, de estoque e financeiro para empresas que compram, vendem '
            .'e emitem nota fiscal. Emite NF-e com ICMS-ST, FCP, IBS, CBS e IS calculados no servidor, '
            .'emite NFS-e pelo SIGISS de Araras, importa compras por XML, fecha o estoque contra a '
            .'contagem física e lança contas a pagar e a receber com baixa.';
        $site = 'https://'.config('produto.dominio');

        // Montado aqui, e não com `@json`, porque o parser de diretiva do Blade
        // não fecha o array multilinha e quebra a view com ParseError.
        // Sem nota, preço ou contagem de clientes: dado que não existe não entra
        // em marcação estruturada, que é onde a mentira fica auditável.
        $dadosEstruturados = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'Venda Redonda',
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => 'Emissor de NF-e e NFS-e, controle de estoque e financeiro',
            'operatingSystem' => 'Navegador',
            'inLanguage' => 'pt-BR',
            'url' => $site,
            'description' => $descricao,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @endphp

    <meta name="description" content="{{ $descricao }}">
    <link rel="canonical" href="{{ $site }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="EmitirAgora">
    <meta property="og:title" content="EmitirAgora, da nota ao caixa">
    <meta property="og:description" content="{{ $descricao }}">
    <meta property="og:url" content="{{ $site }}">
    <meta property="og:locale" content="pt_BR">
    <meta name="twitter:card" content="summary">

    <script type="application/ld+json">{!! $dadosEstruturados !!}</script>

    @if (filled(config('integracao.meta.pixel_id')))
        @include('partials.pixel-meta', ['pixelId' => config('integracao.meta.pixel_id')])
    @endif
</head>
<body class="fonte-inter relative min-h-screen overflow-x-hidden bg-graphite-50 text-graphite-900 antialiased selection:bg-primary-200 selection:text-primary-900">

{{-- $icones, $chip, $rotulo, $titulo, $subtitulo, $cartao, $vidro, $escuro,
     $botaoCheio e $botaoClaro chegam por View::composer, registrado em
     AppServiceProvider, não por @include (que não devolve variável). --}}

<a href="#conteudo"
   class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-graphite-900 focus:px-4 focus:py-2 focus:text-sm focus:text-white">
    Ir para o conteúdo
</a>

{{-- Fundo fixo: três manchas que derivam devagar sob uma trama de pontos.
     Fica atrás de tudo e não intercepta clique. --}}
<div aria-hidden="true" class="pointer-events-none fixed inset-0 z-0 overflow-hidden">
    <div class="deriva-um absolute left-[-12%] top-[-12%] h-[52vw] w-[52vw] rounded-full bg-primary-200/45 blur-[7.5rem] will-change-transform"></div>
    <div class="deriva-dois absolute bottom-[-18%] right-[-10%] h-[62vw] w-[62vw] rounded-full bg-ember-200/25 blur-[8.75rem] will-change-transform"></div>
    <div class="deriva-tres absolute left-[36%] top-[36%] h-[30vw] w-[30vw] rounded-full bg-white/55 blur-[5rem] will-change-transform"></div>
    <div class="trama-pontos absolute inset-0 opacity-[0.22]"></div>
</div>

{{-- ---------------------------------------------------------------- topo --}}
<header class="fixed left-0 right-0 top-0 z-50">
    <nav class="mx-auto max-w-7xl px-6 pt-5">
        <div class="relative overflow-hidden rounded-full border border-white/90 bg-white/84 px-4 py-3 shadow-[0_14px_38px_-22px_rgba(14,27,31,0.42),inset_0_1px_0_rgba(255,255,255,1)] backdrop-blur-2xl">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 rounded-full bg-white/36"></div>

            <div class="relative z-10 flex items-center justify-between gap-4">
                <a href="#conteudo" class="group flex items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-graphite-200 bg-gradient-to-b from-white to-graphite-50 shadow-[0_2px_8px_rgba(14,27,31,0.06),inset_0_1px_0_white]">
                        <x-marca-padrao class="h-5 w-5 text-graphite-900" />
                    </span>
                    <span class="flex flex-col justify-center leading-none">
                        <span class="whitespace-nowrap text-sm font-medium tracking-tight text-primary-600">EmitirAgora</span>
                        <span class="mt-1 hidden whitespace-nowrap text-[11px] font-light text-graphite-500 sm:block">Fiscal · Estoque · Financeiro</span>
                    </span>
                </a>

                <div class="hidden items-center gap-7 text-xs font-normal text-graphite-600 md:flex">
                    @foreach ([
                        ['#recursos', 'Recursos'],
                        ['#sequencia', 'Sequência'],
                        ['#papeis', 'Papéis'],
                        ['#seguranca', 'Segurança'],
                        ['#planos', 'Planos'],
                    ] as [$href, $texto])
                        <a href="{{ $href }}" class="relative transition-colors duration-300 after:absolute after:-bottom-1.5 after:left-0 after:h-px after:w-0 after:bg-primary-600 after:transition-all after:duration-300 hover:text-primary-700 hover:after:w-full">{{ $texto }}</a>
                    @endforeach
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}" class="{{ $botaoClaro }} max-sm:hidden">Entrar no sistema</a>
                    <a href="{{ route('register') }}" class="{{ $botaoCheio }}">Criar conta grátis</a>
                </div>
            </div>
        </div>
    </nav>
</header>

<main id="conteudo" class="relative z-10">

    {{-- ------------------------------------------------------------ herói --}}
    <section class="mx-auto max-w-7xl overflow-x-clip px-6 pb-20 pt-32 md:pt-40">
        <div class="grid items-center gap-12 lg:grid-cols-[1.02fr_0.98fr] lg:gap-16">
            <div class="text-center lg:text-left">
                {{-- Três linhas curtas, como o template: as duas primeiras no peso
                     leve e a terceira, que é o nome da marca, na placa de cor. --}}
                <h1 data-entra="2" class="text-[3.25rem] font-light leading-[0.92] tracking-[-0.075em] text-graphite-900 md:text-[4.5rem] lg:text-[5.25rem]">
                    Emita rápido.<br>
                    Controle tudo.<br>
                    <span class="mt-3 inline-block whitespace-nowrap rounded-2xl bg-gradient-to-b from-primary-600 to-primary-700 px-5 pb-3 pt-1 text-on-primary shadow-[0_18px_40px_-22px_rgba(228,87,46,0.8),inset_0_1px_0_rgba(255,255,255,0.35)]">EmitirAgora.</span>
                </h1>

                <p data-entra="3" class="mx-auto mt-8 max-w-2xl text-base font-light leading-8 text-graphite-600 lg:mx-0 md:text-lg">
                    O <span class="text-primary-600">EmitirAgora</span> existe para que a nota, o estoque e o caixa
                    contem a mesma história no fim do mês. Sistema fiscal, de estoque
                    e financeiro para quem compra, vende e emite nota.
                </p>

                <div data-entra="4" class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row lg:justify-start">
                    <a href="{{ route('register') }}" class="{{ $botaoCheio }} px-6 py-3 text-sm">Criar conta grátis</a>
                    <a href="#recursos" class="{{ $botaoClaro }} px-6 py-3 text-sm">Ver o que ele faz</a>
                </div>

                <div data-entra="5" class="mt-8 flex flex-col flex-wrap items-center justify-center gap-3 text-xs font-light text-graphite-600 sm:flex-row lg:justify-start">
                    @foreach ([
                        ['escudo', 'Cálculo no servidor'],
                        ['caixas', 'Estoque imutável'],
                        ['pasta', 'Isolado por emitente'],
                    ] as [$icone, $texto])
                        <span class="inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-primary-700" aria-hidden="true">{!! $icones[$icone] !!}</svg>
                            {{ $texto }}
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="relative lg:pl-4">
                <div aria-hidden="true" class="absolute -inset-8 rounded-[3rem] bg-gradient-to-br from-primary-200/50 via-white/20 to-ember-200/30 blur-3xl"></div>

                <div data-entra="4" class="relative rounded-[2rem] border border-white bg-white/90 p-4 shadow-[0_30px_80px_-35px_rgba(14,27,31,0.35),inset_0_2px_0_rgba(255,255,255,1)] sm:p-5">
                    <div class="flex items-center gap-1.5 px-2 pb-4 pt-1">
                        <span aria-hidden="true" class="h-2.5 w-2.5 rounded-full bg-danger-300"></span>
                        <span aria-hidden="true" class="h-2.5 w-2.5 rounded-full bg-ember-300"></span>
                        <span aria-hidden="true" class="h-2.5 w-2.5 rounded-full bg-success-300"></span>
                        <span class="fonte-mono ml-2 text-[0.65rem] tracking-[-0.04em] text-graphite-500">PAINEL FINANCEIRO</span>
                    </div>

                    {{-- Captura real da tela /financeiro, não maquete: dados de
                         uma conta de demonstração local, nunca de cliente. --}}
                    <img
                        src="{{ asset('images/painel-financeiro-demo.png') }}"
                        alt="Painel financeiro do EmitirAgora, com saldo em caixa, resultado do mês e títulos a vencer"
                        class="w-full rounded-[1.5rem] border border-white shadow-[0_10px_28px_-20px_rgba(14,27,31,0.3)]"
                    >
                </div>

                <span aria-hidden="true" data-entra="6" class="flutua absolute -top-4 right-14 z-10 hidden items-center gap-1.5 rounded-full border border-white bg-white px-3 py-2 text-xs font-light text-graphite-700 shadow-[0_10px_30px_-12px_rgba(14,27,31,0.4)] sm:inline-flex">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 text-primary-700">{!! $icones['upload'] !!}</svg>
                    Baixa automática
                </span>

                <span aria-hidden="true" data-entra="6" class="flutua absolute -bottom-4 right-8 z-10 hidden items-center gap-1.5 rounded-full border border-white bg-white px-3 py-2 text-xs font-light text-graphite-700 shadow-[0_10px_30px_-12px_rgba(14,27,31,0.4)] sm:inline-flex">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 text-success-600">{!! $icones['check'] !!}</svg>
                    Caixa em dia
                </span>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------- o que tem dentro --}}
    <section data-revelar class="mx-auto max-w-7xl px-6 py-20">
        <div class="mx-auto mb-14 max-w-5xl text-center">
            <p class="{{ $rotulo }}">O QUE TEM DENTRO</p>
            <h2 class="{{ $titulo }}">Fiscal, estoque e financeiro, no mesmo lugar</h2>
            <p class="{{ $subtitulo }}">
                Não são três sistemas integrados depois. É a mesma base de dados
                desde o primeiro registro.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            @foreach ([
                ['recibo', 'Fiscal', 'Emite a nota e corrige quando precisa, sob a regra do seu contador.', ['Emissão de NF-e', 'NFS-e de Araras (SIGISS)', 'Carta de Correção', 'Regras fiscais com vigência']],
                ['caixas', 'Estoque', 'Kardex fiel, corrigido por estorno, nunca reescrito.', ['Kardex, razão imutável', 'Conferência por inventário', 'Importação de XML de compra']],
                ['dinheiro', 'Financeiro', 'Título com vencimento, baixa no caixa, cobrança por Pix.', ['Contas a pagar e a receber', 'Faturas com página pública', 'Cobrança por Pix', 'Fechamento do mês em ZIP']],
            ] as [$icone, $modulo, $texto, $tags])
                <div class="{{ $cartao }}">
                    {!! $chip($icone) !!}
                    <h3 class="mt-5 text-xl font-normal tracking-tight text-graphite-900">{{ $modulo }}</h3>
                    <p class="mt-3 text-sm font-light leading-7 text-graphite-600">{{ $texto }}</p>
                    <ul class="mt-5 flex flex-wrap gap-1.5">
                        @foreach ($tags as $tag)
                            <li class="fonte-mono rounded-full border border-white bg-white/70 px-2.5 py-1 text-[0.65rem] tracking-[-0.04em] text-graphite-600">{{ $tag }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ------------------------------------------------- dores e transformação --}}
    <section data-revelar class="mx-auto max-w-7xl px-6 py-20">
        <div class="relative z-10 flex w-full flex-col gap-y-16">
            <div class="max-w-3xl">
                <p class="{{ $rotulo }}">POR QUE IMPORTA</p>
                <h2 class="text-4xl font-light leading-[1.05] tracking-[-0.05em] text-graphite-900 md:text-5xl lg:text-6xl">
                    Isso não chega organizado sozinho
                </h2>
                <p class="mt-6 max-w-2xl text-base font-light leading-8 text-graphite-600 md:text-lg">
                    É o que acontece antes de existir um sistema que faça a nota, o
                    estoque e o caixa conversarem.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 lg:gap-8">
                @foreach ([
                    ['recibo', 'A nota sai, e ninguém sabe se saiu certa', 'Cálculo feito na hora, sem conferência, e o erro só aparece quando a SEFAZ rejeita.'],
                    ['caixas', 'O estoque diz um número, a contagem física diz outro', 'Sem razão imutável, toda contagem vira desconfiança.'],
                    ['dinheiro', 'A conta existe, mas ninguém sabe se foi paga', 'Lançamento solto, sem baixa, sem saber quanto entrou nem quanto falta.'],
                    ['relogio', 'O contador corre atrás no fim do mês', 'Cada emissão, cada nota de entrada, cada carta de correção, juntadas na mão.'],
                ] as [$icone, $tituloCartao, $texto])
                    <div class="{{ $cartao }}">
                        {!! $chip($icone) !!}
                        <h3 class="mt-5 text-lg font-normal leading-tight tracking-tight text-graphite-900">{{ $tituloCartao }}</h3>
                        <p class="mt-3 text-sm font-light leading-7 text-graphite-600">{{ $texto }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Painel escuro da transformação, no mesmo lugar que o template usa --}}
            <div class="{{ $escuro }} min-h-[560px] lg:min-h-[620px]">
                <div aria-hidden="true" class="deriva-um pointer-events-none absolute -right-24 -top-24 h-[28rem] w-[28rem] rounded-full bg-primary-600/20 blur-[6rem]"></div>

                <div class="relative grid gap-10 p-8 md:p-12 lg:grid-cols-[0.95fr_1.05fr] lg:gap-14">
                    <div>
                        <p class="fonte-mono mb-4 text-xs font-medium tracking-[-0.04em] text-primary-400">A TRANSFORMAÇÃO</p>
                        <h3 class="text-3xl font-light leading-[1.08] tracking-[-0.04em] text-white md:text-5xl">
                            Da divergência ao fechamento redondo
                        </h3>
                        <p class="mt-6 max-w-md text-base font-light leading-8 text-graphite-300">
                            O mesmo evento entra bagunçado e sai organizado: a nota, o
                            estoque e o caixa param de contar histórias diferentes.
                        </p>

                        <div class="mt-10 flex flex-col items-start gap-3 sm:flex-row">
                            <a href="{{ route('register') }}" class="{{ $botaoCheio }} px-6 py-3 text-sm">Criar conta grátis</a>
                            <a href="#sequencia" class="inline-flex items-center justify-center rounded-full border border-white/20 bg-white/5 px-6 py-3 text-sm font-light text-white transition-all duration-300 hover:-translate-y-0.5 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Ver a sequência</a>
                        </div>

                        <div class="mt-10 border-t border-white/10 pt-6">
                            <p class="fonte-mono mb-4 text-xs font-medium tracking-[-0.04em] text-graphite-400">FEITO PARA QUEM EMITE NOTA</p>
                            <ul class="flex flex-wrap gap-2">
                                @foreach (['Comércio', 'Indústria', 'Distribuição', 'Serviços'] as $publico)
                                    <li class="rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-light text-graphite-200">{{ $publico }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-[1.75rem] border border-white/10 bg-white/5 p-5">
                            <p class="fonte-mono mb-4 text-xs tracking-[-0.04em] text-graphite-400">ENTRA ASSIM</p>
                            <ul class="space-y-2.5">
                                @foreach ([
                                    ['upload', 'XML solto, sem padrão'],
                                    ['caixas', 'Contagem que não bate'],
                                    ['dinheiro', 'Título sem baixa'],
                                    ['relogio', 'Fechamento montado à mão'],
                                ] as [$icone, $texto])
                                    <li class="flex items-center gap-3 rounded-2xl bg-white/5 px-3 py-2.5">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/10 text-graphite-300">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">{!! $icones[$icone] !!}</svg>
                                        </span>
                                        <span class="text-sm font-light text-graphite-200">{{ $texto }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="rounded-[1.75rem] border border-primary-500/25 bg-primary-600/10 p-5">
                            <p class="fonte-mono mb-4 text-xs tracking-[-0.04em] text-primary-400">SAI ASSIM</p>
                            <ul class="space-y-2.5">
                                @foreach ([
                                    ['recibo', 'Nota emitida certa'],
                                    ['check', 'Kardex fiel'],
                                    ['painel', 'Caixa conferido'],
                                    ['pasta', 'Pacote pronto pro contador'],
                                ] as [$icone, $texto])
                                    <li class="flex items-center gap-3 rounded-2xl bg-white/5 px-3 py-2.5">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gradient-to-b from-primary-600 to-primary-700 text-on-primary">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">{!! $icones[$icone] !!}</svg>
                                        </span>
                                        <span class="text-sm font-light text-white">{{ $texto }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- --------------------------------------------------------- recursos --}}
    <section id="recursos" data-revelar class="mx-auto max-w-7xl px-6 py-20">
        <div class="mx-auto mb-14 max-w-5xl text-center">
            <p class="{{ $rotulo }}">O QUE ELE FAZ</p>
            <h2 class="{{ $titulo }}">Sete coisas, e elas dependem uma da outra</h2>
            <p class="{{ $subtitulo }}">
                A compra entra, o estoque fecha, a nota sai, o título baixa e o mês
                acaba fechado. Cada etapa é a entrada da seguinte.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['escudo', 'A nota sai certa de primeira', 'ICMS-ST, FCP, IBS, CBS e IS calculados no servidor, nunca no navegador. Se a SEFAZ recusar, o sistema traduz o código do erro em português e diz o que fazer.', 'Cálculo no servidor'],
                ['caixas', 'O estoque bate com a contagem física', 'Movimento nunca é editado nem apagado. Correção é lançamento de estorno, então o Kardex continua sendo registro fiel de tudo o que entrou e saiu.', 'Kardex fiel'],
                ['upload', 'A compra entra pelo XML', 'Solta, em lote ou em ZIP. O fornecedor nasce do próprio arquivo, sem digitar de novo o que já veio na nota.', 'Sem digitar de novo'],
                ['dinheiro', 'Contas a pagar e a receber, com baixa', 'Título com vencimento por parcela, baixa lançada no caixa, cobrança por Pix quando a chave está cadastrada.', 'Pix incluso'],
                ['predio', 'NFS-e de Araras, pelo SIGISS', 'Emitida a partir da parcela da fatura, com PDF, XML e cancelamento.', 'PDF e XML'],
                ['painel', 'Um painel que abre com o que trava', 'Pendência antes de faturamento, porque reemitir sem saber se já saiu duplica nota.', 'Pendência primeiro'],
            ] as [$icone, $tituloCartao, $texto, $selo])
                <div class="{{ $cartao }}">
                    <div class="flex items-start justify-between gap-3">
                        {!! $chip($icone) !!}
                        <span class="fonte-mono rounded-full border border-primary-200 bg-primary-50 px-2.5 py-1 text-[0.65rem] tracking-[-0.04em] text-primary-800">{{ $selo }}</span>
                    </div>
                    <h3 class="mt-5 text-lg font-normal leading-tight tracking-tight text-graphite-900">{{ $tituloCartao }}</h3>
                    <p class="mt-3 text-sm font-light leading-7 text-graphite-600">{{ $texto }}</p>
                </div>
            @endforeach

            {{-- O sétimo recurso ocupa a faixa inteira, no padrão escuro do template --}}
            <div class="{{ $escuro }} md:col-span-2 lg:col-span-3">
                <div aria-hidden="true" class="deriva-dois pointer-events-none absolute -bottom-28 -left-20 h-[26rem] w-[26rem] rounded-full bg-primary-600/15 blur-[6rem]"></div>

                <div class="relative grid gap-10 p-6 md:grid-cols-[1fr_1.1fr] md:p-10">
                    <div>
                        <p class="fonte-mono mb-4 text-xs font-medium tracking-[-0.04em] text-primary-400">FIM DO MÊS</p>
                        <h3 class="text-3xl font-light leading-[1.08] tracking-[-0.04em] text-white md:text-4xl">
                            O fechamento sai pronto, não é montado
                        </h3>
                        <p class="mt-5 max-w-md text-base font-light leading-8 text-graphite-300">
                            O contador recebe fechado: o período inteiro em um ZIP, com
                            emitidas, canceladas, cartas de correção, inutilizações e
                            entradas, e um resumo que abre no Excel em português sem
                            acento quebrado.
                        </p>
                    </div>

                    <div class="rounded-[1.75rem] border border-white bg-white p-5 shadow-[0_30px_70px_-40px_rgba(0,0,0,0.9)]">
                        <p class="text-sm font-light text-graphite-500">
                            Fechamento do mês, exemplo
                            <span class="mt-1 block font-normal text-graphite-800">Agosto de 2026</span>
                        </p>

                        <div class="mt-5 grid grid-cols-3 gap-3">
                            <div class="rounded-2xl bg-graphite-50 p-4">
                                <p class="fonte-mono text-[0.65rem] tracking-[-0.04em] text-graphite-500">EMITIDAS</p>
                                <p class="num mt-2 text-2xl font-light text-graphite-900">184</p>
                            </div>
                            <div class="rounded-2xl bg-graphite-50 p-4">
                                <p class="fonte-mono text-[0.65rem] tracking-[-0.04em] text-graphite-500">PENDENTES</p>
                                <p class="num mt-2 text-2xl font-light text-graphite-900">0</p>
                            </div>
                            <div class="rounded-2xl bg-gradient-to-b from-primary-600 to-primary-700 p-4">
                                <p class="fonte-mono text-[0.65rem] tracking-[-0.04em] text-on-primary">PACOTE</p>
                                <p class="mt-2 text-sm font-medium text-on-primary">Pronto</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ----------------------------------------------------------- sequência --}}
    <section id="sequencia" data-revelar class="mx-auto max-w-7xl px-6 py-20">
        <div class="mx-auto mb-16 max-w-5xl text-center">
            <p class="{{ $rotulo }}">COMO FUNCIONA</p>
            <h2 class="{{ $titulo }}">Da compra até o contador</h2>
            <p class="{{ $subtitulo }}">
                É uma volta só, e cada etapa é a entrada da seguinte. Nenhuma pede
                digitação do que já estava na nota do fornecedor.
            </p>
        </div>

        <div class="{{ $vidro }} px-6 pb-12 pt-16 md:px-10">
            <div aria-hidden="true" class="deriva-um pointer-events-none absolute left-[10%] top-[-35%] h-[32rem] w-[32rem] rounded-full bg-primary-200/40 blur-[6rem]"></div>
            <div aria-hidden="true" class="deriva-dois pointer-events-none absolute bottom-[-35%] right-[5%] h-[30rem] w-[30rem] rounded-full bg-ember-200/25 blur-[6rem]"></div>
            <div aria-hidden="true" class="trama-pontos pointer-events-none absolute inset-0 opacity-[0.16]"></div>

            <div class="relative">
                {{-- Linha de base sempre visível, cheia. O brilho que percorre
                     ela (.linha-fluxo) é um acréscimo, nunca a única coisa que
                     desenha a linha: uma foto parada pega o brilho em trânsito
                     na metade do percurso, e sem a base a linha inteira some. --}}
                <div aria-hidden="true" class="pointer-events-none absolute left-10 right-10 top-6 hidden h-px bg-gradient-to-r from-primary-200 via-primary-300 to-primary-200 lg:block"></div>
                <div aria-hidden="true" class="pointer-events-none absolute left-10 right-10 top-6 hidden h-px overflow-hidden lg:block">
                    <div class="linha-fluxo absolute inset-y-0 w-28 bg-gradient-to-r from-transparent via-primary-600 to-transparent"></div>
                </div>

                <ol class="relative grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach ([
                        ['upload', 'Chega a compra', 'O XML do fornecedor entra solto, em lote ou em ZIP. O fornecedor é criado a partir do próprio arquivo.', 'XML recebido'],
                        ['check', 'Confere quem é o quê', 'O produto do fornecedor é ligado ao seu. Na segunda nota do mesmo fornecedor, ele já entra reconhecido.', 'Vínculo salvo'],
                        ['caixas', 'Entra no estoque', 'Só depois da sua confirmação. Registrar e confirmar são etapas separadas de propósito.', 'Kardex atualizado'],
                        ['recibo', 'Sai a venda', 'A nota é montada, os tributos são calculados no servidor e a baixa acontece na transmissão.', 'Nota autorizada'],
                        ['pasta', 'Fecha o mês', 'O pacote do período sai pronto para o contador, sem ninguém montar pasta à mão.', 'ZIP pronto'],
                    ] as $i => [$icone, $tituloEtapa, $texto, $selo])
                        <li class="flex min-w-0 flex-col items-center text-center">
                            <span class="num fonte-mono relative z-10 mb-6 flex h-12 w-12 items-center justify-center rounded-full border border-primary-800 bg-gradient-to-b from-primary-600 to-primary-700 text-sm text-on-primary shadow-[0_10px_22px_-10px_rgba(228,87,46,0.6),inset_0_1px_0_rgba(255,255,255,0.35)]">
                                {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}
                            </span>

                            {{-- flex flex-1 flex-col + mt-auto no selo: os cinco
                                 textos têm tamanhos diferentes, e sem isso o
                                 selo de cada card ficava numa altura diferente,
                                 serrilhado. Com isso todos os selos alinham na
                                 mesma linha de base, do card mais alto. --}}
                            <div class="{{ $cartao }} flex w-full flex-1 flex-col">
                                {!! $chip($icone, 'w-10 h-10') !!}
                                <h3 class="mt-4 text-base font-normal tracking-tight text-graphite-900">{{ $tituloEtapa }}</h3>
                                <p class="mt-2 text-sm font-light leading-7 text-graphite-600">{{ $texto }}</p>
                                <span class="fonte-mono mt-4 inline-flex items-center gap-1.5 self-start rounded-full border border-primary-200 bg-primary-50 px-2.5 py-1 text-[0.65rem] tracking-[-0.04em] text-primary-800 lg:mt-auto">
                                    <span aria-hidden="true" class="h-1 w-1 rounded-full bg-primary-600"></span>
                                    {{ $selo }}
                                </span>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="relative mt-12 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="{{ route('register') }}" class="{{ $botaoCheio }} px-6 py-3 text-sm">Criar conta grátis</a>
                <a href="#planos" class="{{ $botaoClaro }} px-6 py-3 text-sm">Ver os planos</a>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------------- papéis --}}
    <section id="papeis" data-revelar class="mx-auto max-w-7xl px-6 py-20">
        <div class="mx-auto mb-14 max-w-5xl text-center">
            <p class="{{ $rotulo }}">PARA CADA PAPEL</p>
            <h2 class="{{ $titulo }}">Cada papel vê só o que precisa</h2>
            <p class="{{ $subtitulo }}">
                A permissão é por emitente e por perfil. Ninguém vê tela nem dado que
                não é da sua função.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            @foreach ([
                ['recibo', 'Faturamento', 'Emite a nota, vê o estoque, recebe pelo Pix.', ['Emissão de NF-e', 'Contas a receber', 'Estoque, só consulta'], false],
                ['caixas', 'Estoque', 'Confere, ajusta por inventário, nunca edita o passado.', ['Kardex', 'Conferência', 'Importação de XML'], true],
                ['escudo', 'Contador', 'Escreve a regra fiscal, exporta o fechamento.', ['Regras fiscais', 'Exportação do período', 'Financeiro, só consulta'], false],
            ] as [$icone, $papel, $texto, $tags, $destaque])
                <div class="{{ $destaque
                    ? 'relative overflow-hidden rounded-[2rem] border border-primary-200 bg-gradient-to-b from-primary-50 to-white p-6 shadow-[0_15px_35px_-10px_rgba(228,87,46,0.15),inset_0_2px_0_rgba(255,255,255,1)] transition-all duration-500 hover:-translate-y-1'
                    : $cartao }}">
                    {!! $chip($icone) !!}
                    <h3 class="mt-5 text-xl font-normal tracking-tight text-graphite-900">{{ $papel }}</h3>
                    <p class="mt-3 text-sm font-light leading-7 text-graphite-600">{{ $texto }}</p>
                    <ul class="mt-5 flex flex-wrap gap-1.5">
                        @foreach ($tags as $tag)
                            <li class="fonte-mono rounded-full border border-white bg-white/70 px-2.5 py-1 text-[0.65rem] tracking-[-0.04em] text-graphite-600">{{ $tag }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ---------------------------------------------------------- segurança --}}
    <section id="seguranca" data-revelar class="mx-auto max-w-7xl px-6 py-20">
        <div class="mx-auto mb-14 max-w-5xl text-center">
            <p class="{{ $rotulo }}">SEGURANÇA E CONTROLE</p>
            <h2 class="{{ $titulo }}">Seus dados não circulam entre clientes</h2>
            <p class="{{ $subtitulo }}">
                O sistema isola cada emitente por dentro, do banco ao arquivo.
                Certificado digital, senha e XML ficam em disco privado, nunca em
                log, nunca visíveis para outro cliente.
            </p>
        </div>

        <div class="{{ $vidro }}">
            <div aria-hidden="true" class="deriva-um pointer-events-none absolute left-[-10%] top-[-35%] h-[34rem] w-[34rem] rounded-full bg-primary-200/40 blur-[6rem]"></div>
            <div aria-hidden="true" class="deriva-dois pointer-events-none absolute bottom-[-40%] right-[-10%] h-[32rem] w-[32rem] rounded-full bg-ember-200/25 blur-[6rem]"></div>
            <div aria-hidden="true" class="trama-pontos pointer-events-none absolute inset-0 opacity-[0.16]"></div>

            <div class="relative grid gap-8 p-6 md:p-10 lg:grid-cols-[0.9fr_1.1fr] lg:gap-12 lg:p-12">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-white bg-white/75 px-3.5 py-2 shadow-[0_6px_18px_-12px_rgba(14,27,31,0.3),inset_0_1px_0_white]">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 text-primary-700" aria-hidden="true">{!! $icones['escudo'] !!}</svg>
                        <span class="text-xs font-light text-graphite-700">Isolamento por emitente</span>
                    </div>

                    <h3 class="mt-6 text-3xl font-light leading-[1.08] tracking-[-0.04em] text-graphite-900 md:text-4xl">
                        Quem escreve a regra fiscal é o seu contador
                    </h3>

                    <div class="mt-6 space-y-4 text-base font-light leading-8 text-graphite-600">
                        <p>
                            Não existe alíquota, CST, CSOSN ou CFOP escrito dentro do
                            sistema. O contador entra numa tela própria, com permissão
                            própria, e escreve a regra que vale para a sua operação.
                        </p>
                        <p>
                            Toda regra tem data de vigência. Quando a lei muda, a anterior
                            recebe fim de vigência em vez de ser apagada, e a nota de março
                            continua conferindo com a regra de março.
                        </p>
                    </div>

                    <ul class="mt-7 flex flex-wrap gap-2">
                        @foreach ([
                            ['pasta', 'Disco privado'],
                            ['escudo', 'Certificado nunca logado'],
                            ['relogio', 'Homologação por padrão'],
                        ] as [$icone, $tag])
                            <li class="inline-flex items-center gap-1.5 rounded-full border border-white bg-white/70 px-3 py-1.5 text-xs font-light text-graphite-700">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 text-primary-700" aria-hidden="true">{!! $icones[$icone] !!}</svg>
                                {{ $tag }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="rounded-[2rem] border border-white bg-white/90 p-5 shadow-[0_20px_50px_-30px_rgba(14,27,31,0.4)]">
                    <div class="flex items-center gap-1.5 px-1 pb-4">
                        <span aria-hidden="true" class="h-2.5 w-2.5 rounded-full bg-danger-300"></span>
                        <span aria-hidden="true" class="h-2.5 w-2.5 rounded-full bg-ember-300"></span>
                        <span aria-hidden="true" class="h-2.5 w-2.5 rounded-full bg-success-300"></span>
                        <span class="fonte-mono ml-2 text-[0.65rem] tracking-[-0.04em] text-graphite-500">PAINEL DE SEGURANÇA</span>
                    </div>

                    <ul class="space-y-2.5">
                        @foreach ([
                            ['pasta', 'Isolamento por emitente', 'Cada consulta filtra pelo emitente atual', 'ATIVO'],
                            ['escudo', 'Certificado em disco privado', 'Nunca em log, nunca no navegador', 'PROTEGIDO'],
                            ['relogio', 'Ambiente de homologação', 'Produção só por ativação manual', 'PADRÃO'],
                            ['check', 'Alerta de vencimento', 'Avisos em 30, 15 e 7 dias', 'ATIVO'],
                        ] as [$icone, $tituloLinha, $texto, $estado])
                            <li class="flex items-center gap-3 rounded-2xl border border-white bg-white px-4 py-3 shadow-[0_6px_16px_-12px_rgba(14,27,31,0.35)]">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white bg-graphite-50 text-primary-700">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">{!! $icones[$icone] !!}</svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-normal text-graphite-900">{{ $tituloLinha }}</span>
                                    <span class="block text-xs font-light text-graphite-500">{{ $texto }}</span>
                                </span>
                                <span class="fonte-mono shrink-0 rounded-full bg-success-50 px-2.5 py-1 text-[0.65rem] tracking-[-0.04em] text-success-700">{{ $estado }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------------- planos --}}
    <section id="planos" data-revelar class="mx-auto max-w-7xl px-6 py-20">
        <div class="mx-auto mb-14 max-w-5xl text-center">
            <p class="{{ $rotulo }}">PLANOS</p>
            <h2 class="{{ $titulo }}">Um plano para cada estágio do negócio</h2>
            <p class="{{ $subtitulo }}">
                O mesmo sistema fiscal, de estoque e financeiro em todos. O que
                muda é o volume de notas e o tamanho do financeiro.
            </p>
        </div>

        <x-planos />
    </section>

    {{-- ------------------------------------------------------- o que falta --}}
    <section data-revelar class="mx-auto max-w-7xl px-6 py-20">
        <div class="mx-auto mb-14 max-w-5xl text-center">
            <p class="{{ $rotulo }}">TRANSPARÊNCIA</p>
            <h2 class="{{ $titulo }}">O que ele ainda não faz</h2>
            <p class="{{ $subtitulo }}">
                Está aqui porque descobrir depois da contratação é pior do que ler
                agora.
            </p>
        </div>

        <div class="{{ $vidro }} p-6 md:p-10">
            <div aria-hidden="true" class="trama-pontos pointer-events-none absolute inset-0 opacity-[0.16]"></div>

            <div class="relative grid gap-x-10 gap-y-3 sm:grid-cols-2">
                @foreach ([
                    'DRE e fechamento do mês',
                    'Tesouraria: caixa livre, reserva, meses de sobrevivência',
                    'Distribuição DF-e e manifestação do destinatário',
                    'Relatórios gerenciais',
                    'Cálculo de DIFAL',
                    'Contingência SVC',
                ] as $item)
                    <div class="flex items-center gap-3 rounded-2xl border border-white bg-white/70 px-4 py-3 text-sm font-light text-graphite-700">
                        <span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full bg-graphite-400"></span>
                        {{ $item }}
                    </div>
                @endforeach
            </div>

            <p class="relative mt-8 max-w-3xl text-sm font-light leading-7 text-graphite-600">
                O núcleo do financeiro já funciona, por isso a assinatura já diz Financeiro.
                O que falta é a leitura do resultado: DRE e
                tesouraria.
            </p>
        </div>
    </section>

    {{-- --------------------------------------------------------------- faq --}}
    <section id="faq" data-revelar class="mx-auto max-w-7xl px-6 py-20">
        <div class="mx-auto mb-14 max-w-3xl text-center">
            <p class="{{ $rotulo }}">PERGUNTAS</p>
            <h2 class="text-4xl font-normal leading-[1.05] tracking-tight text-graphite-900 md:text-5xl lg:text-6xl">Perguntas frequentes</h2>
            <p class="mt-6 text-base font-light leading-8 text-graphite-600 md:text-lg">
                Respostas práticas sobre emissão, regra fiscal, planos e onde os
                seus dados ficam guardados.
            </p>
        </div>

        <div class="{{ $vidro }} p-4 md:p-6 lg:p-8">
            <div aria-hidden="true" class="deriva-um pointer-events-none absolute left-[-10%] top-[-35%] h-[34rem] w-[34rem] rounded-full bg-primary-200/40 blur-[6rem]"></div>
            <div aria-hidden="true" class="trama-pontos pointer-events-none absolute inset-0 opacity-[0.16]"></div>

            <div class="relative grid items-start gap-6 lg:grid-cols-[0.82fr_1.18fr] lg:gap-8">
                <div class="{{ $escuro }} p-7">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 text-white">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">{!! $icones['escudo'] !!}</svg>
                    </span>
                    <p class="fonte-mono mb-3 mt-6 text-xs font-medium tracking-[-0.04em] text-primary-400">AINDA EM DÚVIDA?</p>
                    <h3 class="text-2xl font-light leading-tight tracking-[-0.04em] text-white">Comece pelo gratuito</h3>
                    <p class="mt-4 text-sm font-light leading-7 text-graphite-300">
                        Cadastro imediato, sem contrato e sem cartão. Se não servir, não
                        custou nada além do tempo de criar a conta.
                    </p>
                    <a href="{{ route('register') }}" class="{{ $botaoCheio }} mt-7 w-full py-3 text-sm">Criar conta grátis</a>
                    <a href="https://wa.me/55199971351777?text=Ol%C3%A1%2C%20quero%20saber%20mais%20sobre%20o%20EmitirAgora" target="_blank" rel="noopener" class="mt-3 block text-center text-xs font-light text-graphite-300 underline decoration-white/30 underline-offset-4 hover:text-white">
                        ou fale no WhatsApp
                    </a>
                </div>

                <div class="space-y-3">
                    @foreach ([
                        ['upload', 'Preciso trocar de sistema fiscal para usar?', 'Sim, é o sistema que emite a nota. Não precisa trocar o resto: importa o que já existe por XML.'],
                        ['escudo', 'Meu contador consegue mexer na regra fiscal sozinho?', 'Sim. Tela própria, permissão própria, sem depender de programador para mudar alíquota ou CST.'],
                        ['predio', 'Serve para matriz e filial?', 'Sim. Um tenant pode ter mais de um emitente, cada um com CNPJ e certificado próprios.'],
                        ['relogio', 'O que ainda falta no sistema?', 'Está listado na seção acima, sem esconder.'],
                        ['dinheiro', 'Dá pra testar antes de pagar?', 'Dá. Você cria a conta e testa o sistema antes de assinar, sem cartão e sem contrato.'],
                        ['pasta', 'Onde ficam os meus dados?', 'No disco privado da conta, isolado por emitente. Certificado e senha nunca são logados.'],
                    ] as [$icone, $pergunta, $resposta])
                        <details class="group rounded-[1.5rem] border border-white bg-white/72 px-5 py-4 shadow-[0_8px_22px_-18px_rgba(14,27,31,0.3),inset_0_1px_0_white] transition-colors duration-300 open:bg-white/90">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 marker:content-none">
                                <span class="flex items-center gap-3">
                                    {!! $chip($icone, 'w-9 h-9') !!}
                                    <span class="text-sm font-normal text-graphite-900 md:text-base">{{ $pergunta }}</span>
                                </span>
                                <span aria-hidden="true" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-white bg-graphite-50 text-graphite-500 transition-transform duration-300 group-open:rotate-45">+</span>
                            </summary>
                            <p class="mt-3 pl-12 text-sm font-light leading-7 text-graphite-600">{{ $resposta }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- --------------------------------------------------------- ação final --}}
    <section data-revelar class="mx-auto max-w-7xl px-6 pb-20 pt-14">
        <div class="relative mx-auto mb-10 max-w-3xl text-center">
            <p class="fonte-mono mb-3 text-xs font-medium tracking-[-0.04em] text-primary-800">ÚLTIMO PASSO</p>
            <h2 class="text-3xl font-normal leading-[1.08] tracking-tight text-graphite-900 md:text-4xl">
                Menos conferência. Mais mês fechado.
            </h2>
            <p class="mt-4 text-sm font-light leading-7 text-graphite-600 md:text-base">
                Da nota ao caixa, a venda fecha redonda.
            </p>
        </div>

        <div class="relative isolate overflow-hidden rounded-[2.75rem] border border-primary-800 bg-gradient-to-b from-primary-500 via-primary-600 to-primary-700 text-on-primary shadow-[0_40px_90px_-45px_rgba(228,87,46,0.72),inset_0_1px_0_rgba(255,255,255,0.34)]">
            <div aria-hidden="true" class="trama-pontos pointer-events-none absolute inset-0 z-0 opacity-[0.13]"></div>
            <div aria-hidden="true" class="deriva-um pointer-events-none absolute left-[-12%] top-[-35%] z-0 h-[34rem] w-[34rem] rounded-full bg-white/26 blur-[6rem]"></div>
            <div aria-hidden="true" class="deriva-dois pointer-events-none absolute bottom-[-40%] right-[-12%] z-0 h-[32rem] w-[32rem] rounded-full bg-graphite-900/18 blur-[6rem]"></div>

            <span aria-hidden="true" class="flutua absolute left-10 top-12 z-20 hidden items-center gap-2 rounded-2xl border border-white bg-white px-4 py-3 text-xs font-light text-graphite-700 shadow-[0_18px_40px_-20px_rgba(14,27,31,0.5)] md:inline-flex">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-success-600">{!! $icones['check'] !!}</svg>
                <span>
                    <span class="block font-normal text-graphite-900">Diferença zero</span>
                    <span class="block text-graphite-500">O mês fecha redondo</span>
                </span>
            </span>

            <span aria-hidden="true" class="flutua absolute bottom-12 right-10 z-20 hidden items-center gap-2 rounded-2xl border border-white bg-white px-4 py-3 text-xs font-light text-graphite-700 shadow-[0_18px_40px_-20px_rgba(14,27,31,0.5)] md:inline-flex">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-primary-700">{!! $icones['pasta'] !!}</svg>
                <span>
                    <span class="block font-normal text-graphite-900">Pacote pronto</span>
                    <span class="block text-graphite-500">Um ZIP para o contador</span>
                </span>
            </span>

            <div class="relative z-10 px-6 py-24 text-center md:px-12 md:py-28">
                <h3 class="mx-auto max-w-3xl text-4xl font-light leading-[1.02] tracking-[-0.05em] md:text-6xl">
                    Da nota ao caixa, a venda fecha redonda
                </h3>
                <p class="mx-auto mt-6 max-w-xl text-base font-light leading-8">
                    Comece grátis, ou entre se já tem acesso.
                </p>

                <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-full border border-white bg-white px-7 py-3 text-sm font-medium text-primary-700 shadow-[0_10px_28px_-14px_rgba(14,27,31,0.5)] transition-all duration-300 hover:-translate-y-0.5 hover:bg-graphite-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                        Criar conta grátis
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-full border border-graphite-900/25 px-7 py-3 text-sm font-normal text-on-primary transition-all duration-300 hover:-translate-y-0.5 hover:bg-graphite-900/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                        Entrar no sistema
                    </a>
                </div>

                <div class="mt-8 flex flex-col flex-wrap items-center justify-center gap-3 text-xs font-normal sm:flex-row">
                    <span>Cadastro imediato, sem contrato</span>
                    <span aria-hidden="true" class="hidden sm:inline">·</span>
                    <span>Dados isolados por emitente</span>
                    <span aria-hidden="true" class="hidden sm:inline">·</span>
                    <span>Homologação por padrão</span>
                </div>
            </div>
        </div>
    </section>
</main>

{{-- ------------------------------------------------------------- rodapé --}}
<footer class="relative z-10 w-full border-t border-white bg-white/72 shadow-[0_-18px_55px_-40px_rgba(14,27,31,0.45),inset_0_1px_0_white] backdrop-blur-xl">
    <div aria-hidden="true" class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary-200 to-transparent"></div>

    <div class="mx-auto max-w-7xl px-6 py-12">
        <div class="grid grid-cols-1 gap-10 lg:grid-cols-[1.2fr_1.8fr] lg:gap-16">
            <div class="flex flex-col items-center text-center lg:items-start lg:text-left">
                <span class="flex items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-graphite-200 bg-gradient-to-b from-white to-graphite-50 shadow-[0_2px_8px_rgba(14,27,31,0.06),inset_0_1px_0_white]">
                        <x-marca-padrao class="h-5 w-5 text-graphite-900" />
                    </span>
                    <span class="flex flex-col justify-center leading-none">
                        <span class="whitespace-nowrap text-sm font-medium tracking-tight text-primary-600">EmitirAgora</span>
                        <span class="mt-1 hidden whitespace-nowrap text-[11px] font-light text-graphite-500 sm:block">Fiscal · Estoque · Financeiro</span>
                    </span>
                </span>

                <p class="mt-5 max-w-sm text-sm font-light leading-7 text-graphite-600">
                    Sistema fiscal, de estoque e financeiro para empresas que compram,
                    vendem e emitem nota fiscal.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-8 text-center sm:grid-cols-3 sm:text-left">
                <div>
                    <p class="fonte-mono mb-4 text-xs tracking-[-0.04em] text-graphite-500">PRODUTO</p>
                    <ul class="space-y-2.5 text-sm font-light text-graphite-600">
                        <li><a href="#recursos" class="transition-colors hover:text-primary-700">Recursos</a></li>
                        <li><a href="#sequencia" class="transition-colors hover:text-primary-700">Sequência</a></li>
                        <li><a href="#papeis" class="transition-colors hover:text-primary-700">Papéis</a></li>
                    </ul>
                </div>

                <div>
                    <p class="fonte-mono mb-4 text-xs tracking-[-0.04em] text-graphite-500">CONFIANÇA</p>
                    <ul class="space-y-2.5 text-sm font-light text-graphite-600">
                        <li><a href="#seguranca" class="transition-colors hover:text-primary-700">Segurança</a></li>
                        <li><a href="#planos" class="transition-colors hover:text-primary-700">Planos</a></li>
                        <li><a href="#faq" class="transition-colors hover:text-primary-700">Perguntas frequentes</a></li>
                    </ul>
                </div>

                <div>
                    <p class="fonte-mono mb-4 text-xs tracking-[-0.04em] text-graphite-500">CONTA</p>
                    <ul class="space-y-2.5 text-sm font-light text-graphite-600">
                        <li><a href="{{ route('register') }}" class="transition-colors hover:text-primary-700">Criar conta grátis</a></li>
                        <li><a href="{{ route('login') }}" class="transition-colors hover:text-primary-700">Entrar no sistema</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-graphite-200/70 pt-6 md:flex-row">
            <p class="text-xs font-light text-graphite-500">© {{ now()->year }} <span class="text-primary-600">EmitirAgora</span>. Todos os direitos reservados.</p>
            <p class="fonte-mono text-xs tracking-[-0.04em] text-graphite-500">FISCAL · ESTOQUE · FINANCEIRO</p>
        </div>
    </div>
</footer>

</body>
</html>
