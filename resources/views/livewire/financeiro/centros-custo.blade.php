@php $podeGerenciar = auth()->user()->can('financeiro.gerenciar'); @endphp

{{-- Padrão de listagem de 09/10/2026: o plano de contas na largura toda,
     criar e editar numa janela. --}}
<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Financeiro"
        title="Centros de custo"
        description="Plano de contas hierárquico, com código de até três níveis, para classificar receita e despesa." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    <x-ui.card :padded="false">
        <x-ui.barra-lista placeholder="Buscar código ou nome">
            @if ($podeGerenciar)
                <x-slot:acao>
                    <x-ui.botao-novo wire:click="novo">Novo centro de custo</x-ui.botao-novo>
                </x-slot:acao>
            @endif
        </x-ui.barra-lista>

        @if ($this->listados->isEmpty())
            <div class="px-6 pb-6">
                <x-ui.empty-state :title="$busca !== '' ? 'Nada encontrado' : 'Nenhum centro de custo cadastrado'"
                    :description="$busca !== '' ? 'Confira o código ou o nome digitado.' : 'Crie o primeiro pelo botão Novo centro de custo.'" />
            </div>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-y border-graphite-100 text-left">
                        <th class="px-6">Código</th>
                        <th class="px-6">Nome</th>
                        <th class="px-6">Natureza</th>
                        <th class="px-6">Essencial</th>
                        <th class="px-6">Situação</th>
                        <th class="px-6 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->listados as $centro)
                        <tr wire:key="centro-{{ $centro->id }}">
                            <td class="num px-6 text-graphite-700" style="padding-left: {{ 1.5 + (\App\Models\CentroCusto::nivelDoCodigo($centro->codigo) - 1) * 1 }}rem">
                                {{ $centro->codigo }}
                            </td>
                            <td class="px-6">
                                <p class="{{ $centro->grupo ? 'font-semibold' : '' }} text-graphite-900">{{ $centro->nome }}</p>
                                @if ($centro->grupo)<p class="text-xs text-graphite-500">Grupo</p>@endif
                            </td>
                            <td class="px-6">
                                <x-ui.pastilha :tom="$centro->natureza === 'receita' ? 'sucesso' : 'perigo'">{{ $centro->natureza === 'receita' ? 'Receita' : 'Despesa' }}</x-ui.pastilha>
                            </td>
                            <td class="px-6 text-graphite-700">
                                {{ $centro->eEssencial() ? 'Sim' : 'Não' }}
                                @if ($centro->essencial === null)
                                    <span class="text-xs text-graphite-400">(herdado)</span>
                                @endif
                            </td>
                            <td class="px-6"><x-ui.pastilha :tom="$centro->ativo ? 'sucesso' : 'neutro'">{{ $centro->ativo ? 'Ativo' : 'Inativo' }}</x-ui.pastilha></td>
                            <td class="px-6 text-right whitespace-nowrap">
                                @if ($podeGerenciar)
                                    <x-ui.botao-icone acao="editar" rotulo="Editar {{ $centro->codigo }} {{ $centro->nome }}" wire:click="editar({{ $centro->id }})" />
                                    @if ($centro->ativo)
                                        <x-ui.botao-icone acao="excluir" rotulo="Excluir {{ $centro->codigo }} {{ $centro->nome }}" wire:click="excluir({{ $centro->id }})"
                                            wire:confirm="Excluir {{ $centro->codigo }} {{ $centro->nome }}? Se já classificar lançamentos ou tiver filhos, só é inativado." />
                                    @else
                                        <x-ui.botao-icone acao="reativar" rotulo="Reativar {{ $centro->codigo }} {{ $centro->nome }}" wire:click="alternarAtivo({{ $centro->id }})" />
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    @if ($formularioAberto)
        <x-ui.modal :titulo="$editandoId ? 'Editar centro de custo' : 'Novo centro de custo'"
            :subtitulo="$editandoId ? 'Código, natureza e centro pai não mudam depois de criados.' : null">
            <form wire:submit="salvar">
                <x-ui.modal-corpo class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Código" for="cc-codigo" required :error="$errors->first('codigo')" hint="Até três grupos de três dígitos: 001, 001.002 ou 001.002.003.">
                        <x-ui.input id="cc-codigo" wire:model="codigo" placeholder="001" :disabled="$editandoId !== null" />
                    </x-ui.field>

                    <x-ui.field label="Nome" for="cc-nome" required :error="$errors->first('nome')">
                        <x-ui.input id="cc-nome" wire:model="nome" placeholder="Ex.: Folha de pagamento" />
                    </x-ui.field>

                    <x-ui.field label="Natureza" for="cc-nat" required :error="$errors->first('natureza')">
                        <x-ui.select id="cc-nat" wire:model.live="natureza" :disabled="$editandoId !== null">
                            <option value="despesa">Despesa</option>
                            <option value="receita">Receita</option>
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field label="Centro pai" for="cc-pai" hint="Só grupos da mesma natureza podem ser pai.">
                        <x-ui.select id="cc-pai" wire:model="paiId" :disabled="$editandoId !== null">
                            <option value="">Nenhum, é raiz</option>
                            @foreach ($this->possiveisPais as $pai)
                                <option value="{{ $pai->id }}">{{ $pai->codigo }} · {{ $pai->nome }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field label="Essencial" for="cc-ess" class="sm:col-span-2" hint="Vazio herda a classificação do pai. Usado no cálculo de reserva de emergência.">
                        <x-ui.select id="cc-ess" wire:model="essencial">
                            <option value="">Herda do pai</option>
                            <option value="1">Sim</option>
                            <option value="0">Não</option>
                        </x-ui.select>
                    </x-ui.field>

                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-graphite-700">
                            <input type="checkbox" wire:model="grupo" class="size-4 accent-graphite-900">
                            É grupo, pode receber filhos
                        </label>
                        @error('grupo')<p class="mt-1 text-xs font-medium text-danger-700">{{ $message }}</p>@enderror
                    </div>
                </x-ui.modal-corpo>
                <x-ui.modal-rodape>
                    <x-ui.button variant="secondary" wire:click="fecharFormulario">Cancelar</x-ui.button>
                    <x-ui.button type="submit">{{ $editandoId ? 'Salvar alterações' : 'Criar centro de custo' }}</x-ui.button>
                </x-ui.modal-rodape>
            </form>
        </x-ui.modal>
    @endif
</div>
