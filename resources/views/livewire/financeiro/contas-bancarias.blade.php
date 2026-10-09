@php $podeGerenciar = auth()->user()->can('financeiro.gerenciar'); @endphp

{{-- Padrão de listagem de 09/10/2026: as contas numa tabela na largura toda,
     o extrato da conta escolhida embaixo, e criar, editar e lançar ajuste em
     janelas. --}}
<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Financeiro"
        title="Contas bancárias"
        description="Onde o dinheiro está de fato, o saldo de cada conta e o extrato de quem abrir uma." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-ui.indicador rotulo="Saldo total" detalhe="Soma do saldo inicial e do razão de todas as contas ativas."
            :valorClasse="$this->saldoTotalCentavos < 0 ? 'text-danger-700' : 'text-graphite-900'"
            icone="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z">
            {{ App\Support\Dinheiro::formatar($this->saldoTotalCentavos) }}
        </x-ui.indicador>
    </div>

    <x-ui.card title="Contas" :padded="false">
        @if ($podeGerenciar)
            <x-slot:actions>
                <x-ui.botao-novo wire:click="novaConta">Nova conta</x-ui.botao-novo>
            </x-slot:actions>
        @endif

        @if ($this->contas->isEmpty())
            <div class="p-6">
                <x-ui.empty-state title="Nenhuma conta cadastrada" description="Crie a primeira pelo botão Nova conta." />
            </div>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-100 text-left">
                        <th class="px-6">Conta</th>
                        <th class="px-6">Tipo</th>
                        <th class="px-6 text-right">Saldo</th>
                        <th class="px-6">Situação</th>
                        <th class="px-6 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->contas as $conta)
                        <tr wire:key="conta-{{ $conta->id }}" @class(['bg-primary-600/[0.04]' => $contaSelecionadaId === $conta->id])>
                            <td class="px-6">
                                <button type="button" wire:click="selecionar({{ $conta->id }})" class="text-left">
                                    <span class="block font-semibold text-graphite-900 hover:underline">{{ $conta->nome }}</span>
                                    <span class="block text-xs text-graphite-500">{{ $conta->banco ?: 'Sem banco informado' }}</span>
                                </button>
                            </td>
                            <td class="px-6 text-graphite-700">{{ ['corrente' => 'Corrente', 'poupanca' => 'Poupança', 'caixa' => 'Caixa'][$conta->tipo] ?? $conta->tipo }}</td>
                            <td @class([
                                'num px-6 text-right font-semibold',
                                'text-graphite-900' => $conta->saldoCentavos() >= 0,
                                'text-danger-700' => $conta->saldoCentavos() < 0,
                            ])>{{ App\Support\Dinheiro::formatar($conta->saldoCentavos()) }}</td>
                            <td class="px-6">
                                <div class="flex flex-wrap gap-1.5">
                                    <x-ui.pastilha :tom="$conta->ativo ? 'sucesso' : 'neutro'">{{ $conta->ativo ? 'Ativa' : 'Inativa' }}</x-ui.pastilha>
                                    @if ($conta->padrao)<x-ui.pastilha tom="marca">Padrão</x-ui.pastilha>@endif
                                </div>
                            </td>
                            <td class="px-6 text-right whitespace-nowrap">
                                <x-ui.botao-icone acao="ver" rotulo="Ver o extrato de {{ $conta->nome }}" wire:click="selecionar({{ $conta->id }})" />
                                @if ($podeGerenciar)
                                    <x-ui.botao-icone acao="editar" rotulo="Editar {{ $conta->nome }}" wire:click="editarConta({{ $conta->id }})" />
                                    @if ($conta->ativo)
                                        <x-ui.botao-icone acao="excluir" rotulo="Excluir {{ $conta->nome }}" wire:click="excluirConta({{ $conta->id }})"
                                            wire:confirm="Excluir {{ $conta->nome }}? Se já tiver lançamentos, só é inativada." />
                                    @else
                                        <x-ui.botao-icone acao="reativar" rotulo="Reativar {{ $conta->nome }}" wire:click="alternarAtiva({{ $conta->id }})" />
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    @if ($this->contaSelecionada)
        <x-ui.card :title="'Extrato de '.$this->contaSelecionada->nome" subtitle="Últimos 50 lançamentos" :padded="false">
            @if ($podeGerenciar)
                <x-slot:actions>
                    <x-ui.button variant="suave" wire:click="abrirAjuste">Ajuste manual</x-ui.button>
                </x-slot:actions>
            @endif
            @if ($this->movimentos->isEmpty())
                <div class="p-6">
                    <x-ui.empty-state title="Nenhum movimento ainda" description="Baixas de título e ajustes manuais aparecem aqui." />
                </div>
            @else
                <x-ui.table>
                    <thead>
                        <tr class="border-b border-graphite-100 text-left">
                            <th class="px-6">Data</th>
                            <th class="px-6">Descrição</th>
                            <th class="px-6">Origem</th>
                            <th class="px-6 text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->movimentos as $movimento)
                            <tr wire:key="movimento-{{ $movimento->id }}">
                                <td class="num px-6 text-graphite-700">{{ $movimento->ocorrido_em->format('d/m/Y') }}</td>
                                <td class="px-6 text-graphite-900">{{ $movimento->descricao }}</td>
                                <td class="px-6 text-graphite-500">
                                    {{ match ($movimento->origem_tipo) {
                                        'conta_pagar' => 'Conta a pagar',
                                        'fatura_parcela' => 'Conta a receber',
                                        'estorno' => 'Estorno',
                                        default => 'Ajuste manual',
                                    } }}
                                </td>
                                <td @class([
                                    'num px-6 text-right font-semibold',
                                    'text-success-700' => $movimento->sentido === 'credito',
                                    'text-danger-700' => $movimento->sentido === 'debito',
                                ])>
                                    {{ $movimento->sentido === 'debito' ? '-' : '+' }}{{ App\Support\Dinheiro::formatar($movimento->valor_centavos) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>
    @endif

    @if ($formularioAberto)
        <x-ui.modal :titulo="$editandoId ? 'Editar conta' : 'Nova conta'" largura="xl">
            <form wire:submit="salvarConta">
                <x-ui.modal-corpo class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Nome" for="cb-nome" required class="sm:col-span-2" :error="$errors->first('nome')">
                        <x-ui.input id="cb-nome" wire:model="nome" placeholder="Conta corrente Banco X" />
                    </x-ui.field>
                    <x-ui.field label="Banco" for="cb-banco" :error="$errors->first('banco')">
                        <x-ui.input id="cb-banco" wire:model="banco" placeholder="Opcional" />
                    </x-ui.field>
                    <x-ui.field label="Tipo" for="cb-tipo" required :error="$errors->first('tipo')">
                        <x-ui.select id="cb-tipo" wire:model="tipo">
                            <option value="corrente">Corrente</option>
                            <option value="poupanca">Poupança</option>
                            <option value="caixa">Caixa</option>
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field label="Saldo inicial" for="cb-saldo"
                        :hint="$this->editandoTemMovimento ? 'A conta já tem lançamentos: o saldo inicial não muda mais.' : 'Em reais, como você digita.'">
                        <x-ui.input id="cb-saldo" wire:model="saldoInicial" placeholder="0,00" numeric :disabled="$this->editandoTemMovimento" />
                    </x-ui.field>
                    <label class="flex items-center gap-2 self-end pb-3 text-sm text-graphite-700">
                        <input type="checkbox" wire:model="padrao" class="size-4 accent-graphite-900">
                        Conta padrão
                    </label>
                </x-ui.modal-corpo>
                <x-ui.modal-rodape>
                    <x-ui.button variant="secondary" wire:click="fecharFormulario">Cancelar</x-ui.button>
                    <x-ui.button type="submit">{{ $editandoId ? 'Salvar alterações' : 'Criar conta' }}</x-ui.button>
                </x-ui.modal-rodape>
            </form>
        </x-ui.modal>
    @endif

    @if ($ajusteAberto && $this->contaSelecionada)
        <x-ui.modal titulo="Ajuste manual" :subtitulo="'Na conta '.$this->contaSelecionada->nome.'. Lançamento sem título: saque, taxa, transferência entre contas.'" fechar="fecharAjuste" largura="xl">
            <form wire:submit="lancarAjuste">
                <x-ui.modal-corpo class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Sentido" for="aj-sentido" required>
                        <x-ui.select id="aj-sentido" wire:model="ajusteSentido">
                            <option value="debito">Saída</option>
                            <option value="credito">Entrada</option>
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field label="Valor" for="aj-valor" required :error="$errors->first('ajusteValor')">
                        <x-ui.input id="aj-valor" wire:model="ajusteValor" placeholder="0,00" numeric />
                    </x-ui.field>
                    <x-ui.field label="Descrição" for="aj-desc" required class="sm:col-span-2" :error="$errors->first('ajusteDescricao')">
                        <x-ui.input id="aj-desc" wire:model="ajusteDescricao" placeholder="Ex.: taxa de manutenção" />
                    </x-ui.field>
                    <x-ui.field label="Data" for="aj-data" required :error="$errors->first('ajusteOcorridoEm')">
                        <x-ui.input id="aj-data" type="date" wire:model="ajusteOcorridoEm" />
                    </x-ui.field>
                </x-ui.modal-corpo>
                <x-ui.modal-rodape>
                    <x-ui.button variant="secondary" wire:click="fecharAjuste">Cancelar</x-ui.button>
                    <x-ui.button type="submit">Lançar ajuste</x-ui.button>
                </x-ui.modal-rodape>
            </form>
        </x-ui.modal>
    @endif
</div>
