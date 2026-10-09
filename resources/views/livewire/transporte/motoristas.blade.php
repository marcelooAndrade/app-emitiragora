<div class="grid gap-6">
    <x-ui.page-header eyebrow="Transporte" title="Motoristas" description="Quem dirige. Nome e CPF vão no MDF-e." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    {{-- Listagem na largura toda; cadastrar e editar abrem a janela abaixo. --}}
    <x-ui.card :padded="false">
        <x-ui.barra-lista placeholder="Buscar por nome ou CPF">
            @can('transporte.operar')
                <x-slot:acao>
                    <x-ui.botao-novo wire:click="novo">Novo motorista</x-ui.botao-novo>
                </x-slot:acao>
            @endcan
        </x-ui.barra-lista>

        @if ($this->listados->isEmpty())
            <div class="px-6 pb-6">
                <x-ui.empty-state :title="$busca !== '' ? 'Nenhum motorista encontrado' : 'Nenhum motorista'"
                    :description="$busca !== '' ? 'Confira o nome ou o CPF digitado.' : 'Cadastre aqui, ou direto na viagem.'" />
            </div>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-y border-graphite-100 text-left">
                        <th class="px-6">Nome</th>
                        <th class="px-6">CPF</th>
                        <th class="px-6">CNH</th>
                        <th class="px-6">Telefone</th>
                        <th class="px-6">Situação</th>
                        <th class="px-6 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->listados as $m)
                        <tr wire:key="m-{{ $m->id }}">
                            <td class="px-6">
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :nome="$m->nome" />
                                    <span class="font-semibold text-graphite-900">{{ $m->nome }}</span>
                                </div>
                            </td>
                            <td class="num px-6 text-graphite-700">{{ $m->cpfFormatado() }}</td>
                            <td class="px-6 text-graphite-700">{{ $m->cnh ?: '—' }}</td>
                            <td class="px-6 text-graphite-700">{{ $m->telefone ?: '—' }}</td>
                            <td class="px-6"><x-ui.pastilha :tom="$m->ativo ? 'sucesso' : 'neutro'">{{ $m->ativo ? 'Ativo' : 'Inativo' }}</x-ui.pastilha></td>
                            <td class="px-6 text-right whitespace-nowrap">
                                @can('transporte.operar')
                                    <x-ui.botao-icone acao="editar" rotulo="Editar {{ $m->nome }}" wire:click="editar({{ $m->id }})" />
                                    @if ($m->ativo)
                                        <x-ui.botao-icone acao="excluir" rotulo="Excluir {{ $m->nome }}" wire:click="excluir({{ $m->id }})"
                                            wire:confirm="Excluir {{ $m->nome }}? Se ele já estiver em alguma viagem, só é inativado." />
                                    @else
                                        <x-ui.botao-icone acao="reativar" rotulo="Reativar {{ $m->nome }}" wire:click="alternarAtivo({{ $m->id }})" />
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    @if ($formularioAberto)
        <x-ui.modal :titulo="$editandoId ? 'Editar motorista' : 'Novo motorista'">
            <form wire:submit="salvar">
                <x-ui.modal-corpo class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Nome" for="mo-nome" required class="sm:col-span-2" :error="$errors->first('nome')">
                        <x-ui.input id="mo-nome" wire:model="nome" maxlength="60" />
                    </x-ui.field>
                    <x-ui.field label="CPF" for="mo-cpf" required :error="$errors->first('cpf')">
                        <x-ui.input id="mo-cpf" wire:model="cpf" inputmode="numeric" maxlength="14" />
                    </x-ui.field>
                    <x-ui.field label="CNH" for="mo-cnh" :error="$errors->first('cnh')">
                        <x-ui.input id="mo-cnh" wire:model="cnh" maxlength="20" />
                    </x-ui.field>
                    <x-ui.field label="Telefone" for="mo-tel" :error="$errors->first('telefone')">
                        <x-ui.input id="mo-tel" wire:model="telefone" maxlength="20" />
                    </x-ui.field>
                    <x-ui.field label="Chave Pix" for="mo-pix" hint="Para pagar o frete do motorista terceiro." :error="$errors->first('chavePix')">
                        <x-ui.input id="mo-pix" wire:model="chavePix" maxlength="77" />
                    </x-ui.field>
                    @if ($this->usaEfrete)
                        <p class="border-t border-graphite-100 pt-4 text-xs font-semibold text-graphite-700 sm:col-span-2">Para o CIOT pelo e-Frete (CNH com 11 dígitos e celular com DDD acima)</p>
                        <x-ui.field label="Nascimento" for="mo-nasc" :error="$errors->first('nascimento')">
                            <x-ui.input id="mo-nasc" type="date" wire:model="nascimento" />
                        </x-ui.field>
                        <x-ui.field label="CEP" for="mo-cep" :hint="$municipio ?: 'Preenche o endereço sozinho.'" :error="$errors->first('cep')">
                            <x-ui.input id="mo-cep" wire:model.blur="cep" inputmode="numeric" maxlength="9" />
                        </x-ui.field>
                        <x-ui.field label="Rua" for="mo-rua" class="sm:col-span-2" :error="$errors->first('logradouro')">
                            <x-ui.input id="mo-rua" wire:model="logradouro" maxlength="60" />
                        </x-ui.field>
                        <x-ui.field label="Número" for="mo-num" :error="$errors->first('numero')">
                            <x-ui.input id="mo-num" wire:model="numero" maxlength="10" />
                        </x-ui.field>
                        <x-ui.field label="Bairro" for="mo-bairro" :error="$errors->first('bairro')">
                            <x-ui.input id="mo-bairro" wire:model="bairro" maxlength="60" />
                        </x-ui.field>
                    @endif
                </x-ui.modal-corpo>
                <x-ui.modal-rodape>
                    <x-ui.button variant="secondary" wire:click="fecharFormulario">Cancelar</x-ui.button>
                    <x-ui.button type="submit">{{ $editandoId ? 'Salvar alterações' : 'Cadastrar motorista' }}</x-ui.button>
                </x-ui.modal-rodape>
            </form>
        </x-ui.modal>
    @endif
</div>
