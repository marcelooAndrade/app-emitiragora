@php
    $fatura = $this->fatura;
    $totais = $this->totais;
    $hoje = today()->toDateString();
@endphp

<div class="grid gap-6">

    <nav class="text-xs text-graphite-500" aria-label="Caminho">
        <a href="{{ route('faturas') }}" wire:navigate class="hover:underline">Faturas</a>
        <span class="mx-1">/</span>
        <span class="text-graphite-900">{{ $fatura->titulo }}</span>
    </nav>

    <x-ui.page-header
        :eyebrow="$fatura->mensalidade ? 'Fatura · Mensalidade do EmitirAgora' : 'Fatura'"
        :title="$fatura->titulo"
        :description="collect([$fatura->destinatario?->razao_social, $fatura->centroCusto ? $fatura->centroCusto->codigo.' · '.$fatura->centroCusto->nome : null])->filter()->implode(' · ') ?: 'Sem cliente vinculado'">
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                @can('financeiro.gerenciar')
                    @if ($fatura->estaAtiva())
                        <x-ui.button variant="secondary" wire:click="abrirEdicao">Editar fatura</x-ui.button>
                    @endif
                @endcan
                <x-ui.button :href="$this->linkPublico" target="_blank" rel="noreferrer">Abrir fatura</x-ui.button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    @error('baixa')
        <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
    @enderror

    @if ($fatura->status === 'cancelada')
        <x-ui.alert variant="danger" title="Fatura cancelada">O link público não abre mais.</x-ui.alert>
    @endif

    {{-- O link é o que vai para o cliente. Fica em destaque, com o copiar
         do lado, porque é a ação que quem abre esta tela mais faz. --}}
    <x-ui.card>
        <div class="flex flex-wrap items-center justify-between gap-3" x-data="{ copiado: false }">
            <div class="min-w-0">
                <p class="etiqueta text-graphite-500">Link permanente para o cliente</p>
                <p class="num mt-1 truncate text-sm text-graphite-900">{{ $this->linkPublico }}</p>
            </div>
            <x-ui.button variant="secondary" size="sm" type="button"
                @click="navigator.clipboard.writeText(@js($this->linkPublico)); copiado = true; setTimeout(() => copiado = false, 2000)">
                <span x-show="!copiado">Copiar link</span>
                <span x-show="copiado" x-cloak>Copiado</span>
            </x-ui.button>
        </div>
    </x-ui.card>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.card>
            <p class="etiqueta text-graphite-500">Valor total</p>
            <p class="num mt-1 text-2xl font-bold text-graphite-900">{{ App\Support\Dinheiro::formatar($totais['total']) }}</p>
        </x-ui.card>
        <x-ui.card>
            <p class="etiqueta text-graphite-500">Recebido</p>
            <p class="num mt-1 text-2xl font-bold text-success-700">{{ App\Support\Dinheiro::formatar($totais['pago']) }}</p>
        </x-ui.card>
        <x-ui.card>
            <p class="etiqueta text-graphite-500">Em aberto</p>
            <p class="num mt-1 text-2xl font-bold text-graphite-900">{{ App\Support\Dinheiro::formatar($totais['emAberto']) }}</p>
        </x-ui.card>
    </div>

    @if (filled($fatura->observacoes))
        <x-ui.card title="Observações para o cliente">
            <p class="whitespace-pre-line text-sm text-graphite-700">{{ $fatura->observacoes }}</p>
        </x-ui.card>
    @endif

    @if ($editando)
        <x-ui.card title="Editar fatura" subtitle="O valor de parcela paga não muda por aqui: reabra a parcela antes.">
            <form wire:submit="salvarEdicao" class="grid gap-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Título" for="ed-titulo" required :error="$errors->first('edTitulo')">
                        <x-ui.input id="ed-titulo" wire:model="edTitulo" maxlength="160" />
                    </x-ui.field>

                    <x-ui.field label="Cliente" for="ed-cli" :error="$errors->first('edPessoaId')">
                        <x-ui.select id="ed-cli" wire:model="edPessoaId">
                            <option value="">Sem cliente vinculado</option>
                            @foreach ($this->clientes as $cliente)
                                <option value="{{ $cliente->id }}">{{ $cliente->razao_social }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    @if ($this->centrosReceita->isNotEmpty())
                        <x-ui.field label="Centro de receita" for="ed-centro" required :error="$errors->first('edCentroCustoId')">
                            <x-ui.select id="ed-centro" wire:model="edCentroCustoId">
                                <option value="">Escolha</option>
                                @foreach ($this->centrosReceita as $centro)
                                    <option value="{{ $centro->id }}">{{ $centro->codigo }} · {{ $centro->nome }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                    @endif

                    <x-ui.field label="Observações para o cliente" for="ed-obs" :error="$errors->first('edObservacoes')" class="sm:col-span-2">
                        <x-ui.textarea id="ed-obs" wire:model="edObservacoes" rows="2" maxlength="1000" />
                    </x-ui.field>
                </div>

                <div class="border-t border-graphite-200 pt-5">
                    <p class="etiqueta mb-3 text-graphite-500">Parcelas</p>
                    <div class="grid gap-3">
                        @foreach ($edLinhas as $i => $linha)
                            <div class="grid min-w-0 gap-3 sm:grid-cols-[2rem_1fr_9rem_10rem] sm:items-start" wire:key="ed-linha-{{ $linha['id'] }}">
                                <p class="num pt-2 text-sm font-semibold text-graphite-500">{{ $linha['numero'] }}</p>

                                <x-ui.field :label="$i === 0 ? 'Descrição' : null" :error="$errors->first('edLinhas.'.$i.'.descricao')">
                                    <x-ui.input wire:model="edLinhas.{{ $i }}.descricao" maxlength="160" />
                                </x-ui.field>

                                <x-ui.field :label="$i === 0 ? 'Valor' : null" :error="$errors->first('edLinhas.'.$i.'.valor')" :hint="$linha['status'] === 'pago' ? 'Paga' : null">
                                    <x-ui.input wire:model="edLinhas.{{ $i }}.valor" :disabled="$linha['status'] === 'pago'" />
                                </x-ui.field>

                                <x-ui.field :label="$i === 0 ? 'Vencimento' : null" :error="$errors->first('edLinhas.'.$i.'.vencimento')">
                                    <x-ui.input type="date" wire:model="edLinhas.{{ $i }}.vencimento" />
                                </x-ui.field>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="submit">Salvar alterações</x-ui.button>
                    <x-ui.button type="button" variant="ghost" wire:click="fecharEdicao">Cancelar</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif


    <x-ui.card title="Parcelas" :subtitle="$fatura->parcelas->count().' '.($fatura->parcelas->count() === 1 ? 'parcela' : 'parcelas')">
        <x-slot:actions>
            @can('financeiro.gerenciar')
                @if ($this->contas->isNotEmpty())
                    <x-ui.field label="Receber na conta" for="fd-conta" class="w-full max-w-xs">
                        <x-ui.select id="fd-conta" wire:model="contaBaixaId">
                            <option value="">Sem conta bancária</option>
                            @foreach ($this->contas as $conta)
                                <option value="{{ $conta->id }}">{{ $conta->nome }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                @endif
            @endcan
        </x-slot:actions>

        <ul class="grid gap-px bg-graphite-200/70">
            @foreach ($fatura->parcelas as $parcela)
                @php
                    $estado = $parcela->status === 'pago' ? 'paga' : ($parcela->status === 'cancelado' ? 'cancelada' : ($parcela->vencimento->toDateString() < $hoje ? 'vencida' : 'aberta'));
                @endphp
                <li class="grid gap-3 bg-white py-4 sm:grid-cols-[2.5rem_minmax(0,1fr)_auto] sm:items-start" wire:key="parcela-{{ $parcela->id }}">
                    <div @class([
                        'num flex size-9 items-center justify-center rounded-md text-sm font-bold',
                        'bg-success-100 text-success-800' => $estado === 'paga',
                        'bg-danger-100 text-danger-800' => $estado === 'vencida',
                        'bg-graphite-100 text-graphite-700' => in_array($estado, ['aberta', 'cancelada'], true),
                    ])>{{ $parcela->numero }}</div>

                    <div class="min-w-0">
                        <p class="font-medium text-graphite-900">{{ $parcela->descricao }}</p>

                        @if ($vencimentoEmEdicaoDe === $parcela->id)
                            <div class="mt-2 flex flex-wrap items-end gap-2">
                                <x-ui.field label="Novo vencimento" :error="$errors->first('novoVencimento')">
                                    <x-ui.input type="date" wire:model="novoVencimento" />
                                </x-ui.field>
                                <x-ui.button size="sm" wire:click="salvarVencimento">Salvar</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="cancelarVencimento">Cancelar</x-ui.button>
                            </div>
                        @else
                            <p class="num text-xs text-graphite-500">
                                Vencimento em {{ $parcela->vencimento->format('d/m/Y') }}
                                @if ($parcela->pago_em) · pago em {{ $parcela->pago_em->format('d/m/Y') }} @endif
                            </p>
                        @endif


                        <div class="mt-2 flex flex-wrap gap-2">
                            @if (filled($parcela->pix_payload) && $estado !== 'paga')
                                <button type="button" x-data="{ copiado: false }"
                                    @click="navigator.clipboard.writeText(@js($parcela->pix_payload)); copiado = true; setTimeout(() => copiado = false, 2000)"
                                    class="inline-flex min-h-8 items-center rounded-md border border-graphite-300 px-3 text-xs font-semibold text-graphite-700 transition-colors hover:bg-graphite-50">
                                    <span x-show="!copiado">Copiar Pix</span>
                                    <span x-show="copiado" x-cloak class="text-success-700">Copiado</span>
                                </button>
                            @endif

                            @can('financeiro.gerenciar')
                                @if ($estado !== 'cancelada' && $fatura->estaAtiva())
                                    <x-ui.button size="sm" variant="ghost" wire:click="iniciarVencimento({{ $parcela->id }})">Alterar data</x-ui.button>

                                    @if ($estado === 'paga')
                                        <x-ui.button size="sm" variant="ghost" wire:click="reabrir({{ $parcela->id }})"
                                            wire:confirm="Reabrir esta parcela? Se houve lançamento no caixa, entra um estorno.">Reabrir</x-ui.button>
                                    @else
                                        <x-ui.button size="sm" :variant="$estado === 'vencida' ? 'destructive' : 'secondary'"
                                            wire:click="baixar({{ $parcela->id }})"
                                            wire:confirm="Registrar o recebimento desta parcela?">Marcar como pago</x-ui.button>
                                    @endif
                                @endif
                            @endcan

                        </div>
                    </div>

                    <div class="flex items-center gap-3 sm:flex-col sm:items-end">
                        <span class="num text-base font-semibold text-graphite-900">{{ App\Support\Dinheiro::formatar($parcela->valor_centavos) }}</span>
                        <span @class([
                            'etiqueta px-2 py-1',
                            'bg-success-100 text-success-800' => $estado === 'paga',
                            'bg-danger-100 text-danger-800' => $estado === 'vencida',
                            'bg-graphite-100 text-graphite-700' => in_array($estado, ['aberta', 'cancelada'], true),
                        ])>{{ ['paga' => 'Pago', 'vencida' => 'Vencido', 'aberta' => 'Em aberto', 'cancelada' => 'Cancelada'][$estado] }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-ui.card>
</div>
