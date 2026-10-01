{{--
    Página "Para contadores": indicação com comissão recorrente.

    Mesma identidade visual da apresentação (reaproveita os tokens via
    View::composer, ver AppServiceProvider), página própria e autossuficiente
    porque é um destino de anúncio, não uma seção da home.

    Sem Livewire na página: o simulador roda em Alpine, trazido só por
    `@livewireScripts` no fim do body, o mesmo recurso que `fatura-publica`
    já usa para o mesmo motivo.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    @include('partials.head', ['marca' => 'EmitirAgora', 'title' => 'Para contadores'])

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500;600&display=swap">

    <meta name="description" content="Indique o EmitirAgora para seus clientes e receba comissão todo mês, enquanto eles continuarem usando.">

    @if (filled(config('integracao.meta.pixel_id')))
        @include('partials.pixel-meta', ['pixelId' => config('integracao.meta.pixel_id')])
    @endif
</head>
<body
    class="fonte-inter relative min-h-screen overflow-x-hidden bg-graphite-50 text-graphite-900 antialiased selection:bg-primary-200 selection:text-primary-900"
    x-data="simuladorDeComissao()"
>

@php
    $niveis = config('planos.comissao.niveis');
    $planosConfig = config('planos.planos');
    $acessoContadorEmTodos = config('planos.acessoDoContadorEmTodosOsPlanosPagos');
    $pendencias = config('planos.pendencias');

    $essencial = collect($planosConfig)->firstWhere('slug', 'essencial');
    $completo = collect($planosConfig)->firstWhere('slug', 'completo');
@endphp

{{-- Fundo fixo, igual ao da apresentação. --}}
<div aria-hidden="true" class="pointer-events-none fixed inset-0 z-0 overflow-hidden">
    <div class="deriva-um absolute left-[-12%] top-[-12%] h-[52vw] w-[52vw] rounded-full bg-primary-200/45 blur-[7.5rem] will-change-transform"></div>
    <div class="deriva-dois absolute bottom-[-18%] right-[-10%] h-[62vw] w-[62vw] rounded-full bg-ember-200/25 blur-[8.75rem] will-change-transform"></div>
    <div class="trama-pontos absolute inset-0 opacity-[0.22]"></div>
</div>

<header class="fixed left-0 right-0 top-0 z-50">
    <nav class="mx-auto max-w-7xl px-6 pt-5">
        <div class="relative overflow-hidden rounded-full border border-white/90 bg-white/84 px-4 py-3 shadow-[0_14px_38px_-22px_rgba(14,27,31,0.42),inset_0_1px_0_rgba(255,255,255,1)] backdrop-blur-2xl">
            <div class="relative z-10 flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="group flex items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-graphite-200 bg-gradient-to-b from-white to-graphite-50 shadow-[0_2px_8px_rgba(14,27,31,0.06),inset_0_1px_0_white]">
                        <x-marca-padrao class="h-5 w-5 text-graphite-900" />
                    </span>
                    <span class="flex flex-col justify-center leading-none">
                        <span class="whitespace-nowrap text-sm font-medium tracking-tight text-primary-600">EmitirAgora</span>
                        <span class="mt-1 hidden whitespace-nowrap text-[11px] font-light text-graphite-500 sm:block">Para contadores</span>
                    </span>
                </a>

                <a href="#quero-ser-parceiro" class="{{ $botaoCheio }}">Quero ser parceiro</a>
            </div>
        </div>
    </nav>
</header>

<main class="relative z-10">

    {{-- -------------------------------------------------------------- herói --}}
    <section class="mx-auto max-w-5xl px-6 pb-16 pt-32 text-center md:pt-40">
        <p class="{{ $rotulo }}">PARA CONTADORES</p>
        <h1 class="text-4xl font-light leading-[1.05] tracking-[-0.05em] text-graphite-900 md:text-6xl">
            Indique o EmitirAgora aos seus clientes e receba todo mês,
            enquanto eles continuarem usando.
        </h1>
        <p class="mx-auto mt-6 max-w-2xl text-base font-light leading-8 text-graphite-600 md:text-lg">
            Comissão recorrente por cliente ativo, acesso ao painel deles e o
            fechamento do mês pronto para o seu trabalho.
        </p>
        <a href="#quero-ser-parceiro" class="{{ $botaoCheio }} mt-8 inline-flex px-6 py-3 text-sm">Quero ser parceiro</a>
    </section>

    {{-- ------------------------------------------------------ como funciona --}}
    <section class="mx-auto max-w-7xl px-6 py-16">
        <div class="mx-auto mb-14 max-w-5xl text-center">
            <p class="{{ $rotulo }}">COMO FUNCIONA</p>
            <h2 class="{{ $titulo }}">Quatro passos, sem complicação</h2>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['pessoas', 'Você se cadastra', 'Preenche o formulário de parceiro, sem custo.'],
                ['recibo', 'Indica seus clientes', 'Manda o link do EmitirAgora para quem você já atende.'],
                ['dinheiro', 'A empresa assina', 'O cliente contrata e paga a mensalidade direto para nós.'],
                ['check', 'Você recebe a comissão', 'Todo mês, enquanto o cliente permanecer ativo.'],
            ] as $i => [$icone, $tituloPasso, $texto])
                <div class="{{ $cartao }}">
                    <span class="num fonte-mono mb-4 flex h-8 w-8 items-center justify-center rounded-full border border-primary-200 bg-primary-50 text-xs text-primary-700">{{ $i + 1 }}</span>
                    {!! $chip($icone) !!}
                    <h3 class="mt-4 text-base font-normal tracking-tight text-graphite-900">{{ $tituloPasso }}</h3>
                    <p class="mt-2 text-sm font-light leading-7 text-graphite-600">{{ $texto }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- --------------------------------------------------------- comissão --}}
    <section class="mx-auto max-w-7xl px-6 py-16">
        <div class="mx-auto mb-14 max-w-5xl text-center">
            <p class="{{ $rotulo }}">COMISSÃO POR NÍVEL</p>
            <h2 class="{{ $titulo }}">Quanto mais clientes ativos, maior o percentual</h2>
            <p class="{{ $subtitulo }}">
                O percentual do nível vale para todos os seus clientes
                ativos, não só para quem passou do teto anterior.
            </p>
        </div>

        <div class="mx-auto grid max-w-5xl gap-6 sm:grid-cols-3">
            @foreach ($niveis as $nivel)
                <div class="{{ $cartao }} text-center">
                    <p class="fonte-mono text-xs font-medium tracking-[-0.04em] text-primary-700">{{ mb_strtoupper($nivel['nome']) }}</p>
                    <p class="num mt-3 text-4xl font-normal text-graphite-900">{{ $nivel['percentual'] }}%</p>
                    <p class="mt-3 text-sm font-light text-graphite-600">
                        @if ($nivel['ate'] === null)
                            A partir de {{ $nivel['de'] }} clientes ativos
                        @else
                            De {{ $nivel['de'] }} a {{ $nivel['ate'] }} clientes ativos
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ----------------------------------------------------- o que recebe --}}
    <section class="mx-auto max-w-7xl px-6 py-16">
        <div class="mx-auto mb-14 max-w-5xl text-center">
            <p class="{{ $rotulo }}">ALÉM DA COMISSÃO</p>
            <h2 class="{{ $titulo }}">O que o contador recebe</h2>
        </div>

        <div class="mx-auto grid max-w-5xl gap-6 sm:grid-cols-3">
            <div class="{{ $cartao }}">
                {!! $chip('painel') !!}
                <h3 class="mt-4 text-base font-normal tracking-tight text-graphite-900">Painel dos seus clientes</h3>
                <p class="mt-2 text-sm font-light leading-7 text-graphite-600">
                    @if ($acessoContadorEmTodos)
                        Acompanha os clientes do Essencial e do Completo.
                    @else
                        Acompanha os clientes do plano Completo.
                    @endif
                </p>
            </div>

            <div class="{{ $cartao }}">
                {!! $chip('painel') !!}
                <h3 class="mt-4 text-base font-normal tracking-tight text-graphite-900">DRE dos clientes do Completo</h3>
                <p class="mt-2 text-sm font-light leading-7 text-graphite-600">Resultado pronto, sem montar planilha.</p>
            </div>

            <div class="{{ $cartao }}">
                {!! $chip('escudo') !!}
                <h3 class="mt-4 text-base font-normal tracking-tight text-graphite-900">Suporte direto</h3>
                <p class="mt-2 text-sm font-light leading-7 text-graphite-600">
                    @if (filled($pendencias['canalSuporteContador']))
                        {{ $pendencias['canalSuporteContador'] }}
                    @else
                        Canal ainda não definido. Pendência de operação, não de produto.
                    @endif
                </p>
            </div>
        </div>
    </section>

    {{-- -------------------------------------------------------- simulador --}}
    <section class="mx-auto max-w-5xl px-6 py-16">
        <div class="{{ $vidro }} p-6 md:p-10">
            <p class="{{ $rotulo }}">SIMULADOR</p>
            <h2 class="text-3xl font-normal tracking-tight text-graphite-900 md:text-4xl">Quanto você receberia por mês</h2>
            <p class="mt-3 text-sm font-light text-graphite-600">Valores ilustrativos, calculados com os preços dos planos atuais.</p>

            <div class="mt-8 grid gap-6 sm:grid-cols-2">
                <label class="block">
                    <span class="fonte-mono text-xs font-medium tracking-[-0.04em] text-graphite-600">CLIENTES NO ESSENCIAL (R${{ number_format($essencial['precoCentavos'] / 100, 0, ',', '.') }}/mês)</span>
                    <input
                        type="number" min="0" x-model.number="clientesEssencial"
                        class="mt-2 w-full rounded-xl border border-graphite-200 bg-white px-4 py-3 text-sm text-graphite-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200"
                    >
                </label>

                <label class="block">
                    <span class="fonte-mono text-xs font-medium tracking-[-0.04em] text-graphite-600">CLIENTES NO COMPLETO (R${{ number_format($completo['precoCentavos'] / 100, 0, ',', '.') }}/mês)</span>
                    <input
                        type="number" min="0" x-model.number="clientesCompleto"
                        class="mt-2 w-full rounded-xl border border-graphite-200 bg-white px-4 py-3 text-sm text-graphite-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200"
                    >
                </label>
            </div>

            <div class="mt-8 grid gap-6 border-t border-graphite-200/70 pt-8 sm:grid-cols-3">
                <div>
                    <p class="text-xs font-light text-graphite-500">Nível</p>
                    <p class="mt-1 text-xl font-normal text-graphite-900" x-text="nivel.nome"></p>
                </div>
                <div>
                    <p class="text-xs font-light text-graphite-500">Percentual</p>
                    <p class="mt-1 text-xl font-normal text-graphite-900" x-text="nivel.percentual + '%'"></p>
                </div>
                <div>
                    <p class="text-xs font-light text-graphite-500">Comissão mensal estimada</p>
                    <p class="num mt-1 text-xl font-medium text-primary-700" x-text="formatarReal(comissaoMensalCentavos)"></p>
                </div>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------------ regras --}}
    <section class="mx-auto max-w-5xl px-6 py-16">
        <div class="{{ $cartao }}">
            <p class="{{ $rotulo }}">REGRAS</p>
            <ul class="mt-2 space-y-3 text-sm font-light leading-7 text-graphite-600">
                <li>A comissão é paga mensalmente
                    @if (filled($pendencias['diaPagamentoComissao']))
                        , até o dia {{ $pendencias['diaPagamentoComissao'] }},
                    @else
                        (dia do pagamento ainda não definido, pendência de operação)
                    @endif
                    sobre o valor efetivamente pago pelo cliente.
                </li>
                <li>Se o cliente cancelar, a comissão dele termina.</li>
            </ul>
        </div>
    </section>

    {{-- ------------------------------------------------------------- planos --}}
    <section class="mx-auto max-w-7xl px-6 py-16">
        <div class="mx-auto mb-14 max-w-5xl text-center">
            <p class="{{ $rotulo }}">PLANOS PARA MOSTRAR AO SEU CLIENTE</p>
            <h2 class="{{ $titulo }}">O que cada plano inclui</h2>
        </div>

        <x-planos />
    </section>

    {{-- -------------------------------------------------- quero ser parceiro --}}
    <section id="quero-ser-parceiro" class="mx-auto max-w-3xl px-6 py-16">
        <div class="{{ $cartao }}">
            <p class="{{ $rotulo }}">QUERO SER PARCEIRO</p>
            <h2 class="text-3xl font-normal tracking-tight text-graphite-900">Cadastre-se como parceiro</h2>

            @if (blank($pendencias['linkCadastroParceiro']))
                <p class="mt-3 rounded-xl border border-ember-200 bg-ember-50 px-4 py-3 text-sm font-light text-graphite-700">
                    Pendência de operação: este formulário ainda não tem para onde enviar. Preencher por enquanto não cadastra o parceiro de verdade.
                </p>
            @endif

            <form
                @if (filled($pendencias['linkCadastroParceiro'])) action="{{ $pendencias['linkCadastroParceiro'] }}" @endif
                method="POST" class="mt-6 space-y-5"
            >
                @csrf

                <label class="block">
                    <span class="text-sm font-normal text-graphite-700">Nome</span>
                    <input type="text" name="nome" required class="mt-2 w-full rounded-xl border border-graphite-200 bg-white px-4 py-3 text-sm text-graphite-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200">
                </label>

                <label class="block">
                    <span class="text-sm font-normal text-graphite-700">Escritório</span>
                    <input type="text" name="escritorio" required class="mt-2 w-full rounded-xl border border-graphite-200 bg-white px-4 py-3 text-sm text-graphite-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200">
                </label>

                <label class="block">
                    <span class="text-sm font-normal text-graphite-700">E-mail</span>
                    <input type="email" name="email" required class="mt-2 w-full rounded-xl border border-graphite-200 bg-white px-4 py-3 text-sm text-graphite-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200">
                </label>

                <label class="block">
                    <span class="text-sm font-normal text-graphite-700">WhatsApp</span>
                    <input type="tel" name="whatsapp" required class="mt-2 w-full rounded-xl border border-graphite-200 bg-white px-4 py-3 text-sm text-graphite-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200">
                </label>

                <label class="block">
                    <span class="text-sm font-normal text-graphite-700">Quantidade aproximada de clientes</span>
                    <input type="number" min="0" name="quantidade_clientes" required class="mt-2 w-full rounded-xl border border-graphite-200 bg-white px-4 py-3 text-sm text-graphite-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200">
                </label>

                <button type="submit" class="{{ $botaoCheio }} w-full py-3 text-sm" @if (blank($pendencias['linkCadastroParceiro'])) disabled @endif>
                    Quero ser parceiro
                </button>
            </form>
        </div>
    </section>

</main>

<footer class="relative z-10 mx-auto max-w-7xl px-6 pb-10 pt-6 text-center">
    <p class="text-xs font-light text-graphite-500">© {{ now()->year }} <span class="text-primary-600">EmitirAgora</span>. Todos os direitos reservados.</p>
</footer>

<script>
    function simuladorDeComissao() {
        const niveis = @json($niveis);
        const precoEssencialCentavos = {{ $essencial['precoCentavos'] }};
        const precoCompletoCentavos = {{ $completo['precoCentavos'] }};

        return {
            clientesEssencial: 5,
            clientesCompleto: 3,

            get totalClientes() {
                return (this.clientesEssencial || 0) + (this.clientesCompleto || 0);
            },

            get nivel() {
                const encontrado = niveis.find((n) => this.totalClientes >= n.de && (n.ate === null || this.totalClientes <= n.ate));
                return encontrado ?? niveis[0];
            },

            get receitaMensalCentavos() {
                return (this.clientesEssencial || 0) * precoEssencialCentavos + (this.clientesCompleto || 0) * precoCompletoCentavos;
            },

            get comissaoMensalCentavos() {
                return Math.round(this.receitaMensalCentavos * (this.nivel.percentual / 100));
            },

            formatarReal(centavos) {
                return 'R$' + (centavos / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
        };
    }
</script>

@livewireScripts
</body>
</html>
