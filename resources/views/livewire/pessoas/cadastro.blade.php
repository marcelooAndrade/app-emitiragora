{{-- Padrão de listagem de 09/10/2026: a lista na largura toda, com busca e
     filtro por papel; "Novo cadastro" e o lápis de cada linha abrem o
     formulário numa janela. Substitui a lista estreita ao lado de um
     formulário fixo, em que o campo de CNPJ e o botão Buscar não cabiam. --}}
<div class="grid gap-6">
    <x-ui.page-header eyebrow="Cadastros" title="Clientes e parceiros"
        description="Cliente, fornecedor e transportadora ficam no mesmo cadastro. A mesma empresa pode ter mais de um papel." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    <x-ui.card :padded="false">
        <x-ui.barra-lista placeholder="Buscar nome ou documento">
            <x-slot:filtros>
                <x-ui.select wire:model.live="papel" aria-label="Papel" class="rounded-full!">
                    <option value="todos">Todos os papéis</option>
                    <option value="clientes">Clientes</option>
                    <option value="fornecedores">Fornecedores</option>
                    <option value="transportadoras">Transportadoras</option>
                </x-ui.select>
            </x-slot:filtros>
            @can('pessoa.gerenciar')
                <x-slot:acao>
                    <x-ui.botao-novo wire:click="novo">Novo cadastro</x-ui.botao-novo>
                </x-slot:acao>
            @endcan
        </x-ui.barra-lista>

        @if ($this->pessoas->isEmpty())
            <div class="px-6 pb-6">
                <x-ui.empty-state :title="$busca !== '' || $papel !== 'todos' ? 'Nada encontrado' : 'Nenhum cadastro'"
                    :description="$busca !== '' || $papel !== 'todos' ? 'Confira a busca ou o filtro de papel.' : 'Os clientes também entram sozinhos ao faturar uma viagem.'" />
            </div>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-y border-graphite-100 text-left">
                        <th class="px-6">Nome</th>
                        <th class="px-6">Papéis</th>
                        <th class="px-6">Cidade</th>
                        <th class="px-6">Contato</th>
                        <th class="px-6">Situação</th>
                        <th class="px-6 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->pessoas as $pessoa)
                        @php $nome = $pessoa->nome_fantasia ?: $pessoa->razao_social; @endphp
                        <tr wire:key="pessoa-{{ $pessoa->id }}">
                            <td class="px-6">
                                <div class="flex min-w-56 items-center gap-3">
                                    <x-ui.avatar :nome="$nome" />
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-graphite-900">{{ $nome }}</p>
                                        <p class="num truncate text-xs text-graphite-500">{{ $pessoa->documentoFormatado() }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6">
                                <div class="flex flex-wrap gap-1.5">
                                    @if ($pessoa->e_cliente)<x-ui.pastilha tom="marca">Cliente</x-ui.pastilha>@endif
                                    @if ($pessoa->e_fornecedor)<x-ui.pastilha>Fornecedor</x-ui.pastilha>@endif
                                    @if ($pessoa->e_transportadora)<x-ui.pastilha>Transportadora</x-ui.pastilha>@endif
                                </div>
                            </td>
                            <td class="px-6 text-graphite-700">{{ $pessoa->municipio ? $pessoa->municipio.'/'.$pessoa->uf : '—' }}</td>
                            <td class="px-6 text-graphite-700">
                                <span class="block">{{ $pessoa->telefone ?: '—' }}</span>
                                @if ($pessoa->email)<span class="block truncate text-xs text-graphite-500">{{ $pessoa->email }}</span>@endif
                            </td>
                            <td class="px-6"><x-ui.pastilha :tom="$pessoa->ativo ? 'sucesso' : 'neutro'">{{ $pessoa->ativo ? 'Ativo' : 'Inativo' }}</x-ui.pastilha></td>
                            <td class="px-6 text-right whitespace-nowrap">
                                <x-ui.botao-icone acao="editar" rotulo="Editar {{ $nome }}" wire:click="editar({{ $pessoa->id }})" />
                                @can('pessoa.gerenciar')
                                    @if ($pessoa->ativo)
                                        <x-ui.botao-icone acao="excluir" rotulo="Excluir {{ $nome }}" wire:click="excluir({{ $pessoa->id }})"
                                            wire:confirm="Excluir {{ $nome }}? Se já estiver em CT-e, fatura ou conta a pagar, só é inativado." />
                                    @else
                                        <x-ui.botao-icone acao="reativar" rotulo="Reativar {{ $nome }}" wire:click="reativar({{ $pessoa->id }})" />
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            @if ($this->pessoas->hasPages())
                <div class="border-t border-graphite-100 px-6 py-4">{{ $this->pessoas->links() }}</div>
            @endif
        @endif
    </x-ui.card>

    @if ($formularioAberto)
        @php $podeGerenciar = auth()->user()->can('pessoa.gerenciar'); @endphp
        <x-ui.modal :titulo="$editandoId ? 'Editar cadastro' : 'Novo cadastro'" largura="4xl">
            <form wire:submit="salvar">
                <x-ui.modal-corpo>
                    <fieldset @disabled(! $podeGerenciar) class="grid gap-5">
                        @if ($avisoSituacao)
                            <x-ui.alert variant="warning" title="Situação cadastral: {{ $avisoSituacao }}">
                                A Receita informa que esta empresa não está ativa. O cadastro é permitido, mas confira antes de emitir.
                            </x-ui.alert>
                        @endif

                        {{-- Identificação --}}
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Tipo" for="p-tipo">
                                <x-ui.select id="p-tipo" wire:model.live="form.tipo_pessoa">
                                    @foreach (\App\Enums\Fiscal\TipoPessoa::cases() as $tipo)
                                        <option value="{{ $tipo->value }}">{{ $tipo->rotulo() }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>

                            <x-ui.field
                                :label="$form['tipo_pessoa'] === 'F' ? 'CPF' : 'CNPJ'"
                                for="p-doc" required
                                :hint="$form['tipo_pessoa'] === 'J' ? 'Aceita o formato alfanumérico da Receita. Buscar preenche o resto.' : null"
                                :error="$errors->first('documento')">
                                <div class="flex gap-2">
                                    <x-ui.input id="p-doc" wire:model="form.documento" numeric class="min-w-0 flex-1" />
                                    @if ($form['tipo_pessoa'] === 'J')
                                        <x-ui.button variant="secondary" wire:click="buscarCnpj" wire:loading.attr="disabled" wire:target="buscarCnpj" class="shrink-0">
                                            <span wire:loading.remove wire:target="buscarCnpj">Buscar</span>
                                            <span wire:loading wire:target="buscarCnpj">Buscando...</span>
                                        </x-ui.button>
                                    @endif
                                </div>
                            </x-ui.field>

                            <x-ui.field
                                :label="$form['tipo_pessoa'] === 'F' ? 'Nome' : 'Razão social'"
                                for="p-razao" required :error="$errors->first('razao_social')">
                                <x-ui.input id="p-razao" wire:model="form.razao_social" />
                            </x-ui.field>

                            <x-ui.field label="Nome fantasia" for="p-fant">
                                <x-ui.input id="p-fant" wire:model="form.nome_fantasia" />
                            </x-ui.field>
                        </div>

                        {{-- Situação fiscal --}}
                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ui.field label="Indicador de IE" for="p-ind" required>
                                <x-ui.select id="p-ind" wire:model.live="form.ind_ie_dest">
                                    @foreach (\App\Enums\Fiscal\IndIEDest::cases() as $ind)
                                        <option value="{{ $ind->value }}">{{ $ind->value }} - {{ $ind->rotulo() }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>

                            <x-ui.field
                                label="Inscrição Estadual" for="p-ie"
                                :required="$form['ind_ie_dest'] === '1'"
                                :hint="$form['ind_ie_dest'] !== '1' ? 'Deve ficar vazia neste indicador.' : null"
                                :error="$errors->first('inscricao_estadual')">
                                <x-ui.input id="p-ie" wire:model="form.inscricao_estadual" numeric
                                    :disabled="$form['ind_ie_dest'] !== '1'" />
                            </x-ui.field>

                            <x-ui.field label="SUFRAMA" for="p-suf">
                                <x-ui.input id="p-suf" wire:model="form.suframa" numeric />
                            </x-ui.field>
                        </div>

                        {{-- Endereço --}}
                        <div class="grid gap-4 sm:grid-cols-4">
                            <x-ui.field label="CEP" for="p-cep" required class="sm:col-span-2" :error="$errors->first('cep')">
                                <div class="flex gap-2">
                                    <x-ui.input id="p-cep" wire:model="form.cep" numeric class="min-w-0 flex-1" />
                                    <x-ui.button variant="secondary" wire:click="buscarCep" wire:loading.attr="disabled" wire:target="buscarCep" class="shrink-0">
                                        <span wire:loading.remove wire:target="buscarCep">Buscar CEP</span>
                                        <span wire:loading wire:target="buscarCep">Buscando...</span>
                                    </x-ui.button>
                                </div>
                            </x-ui.field>

                            <x-ui.field label="Número" for="p-num" required :error="$errors->first('numero')">
                                <x-ui.input id="p-num" wire:model="form.numero" />
                            </x-ui.field>

                            <x-ui.field label="Complemento" for="p-comp">
                                <x-ui.input id="p-comp" wire:model="form.complemento" />
                            </x-ui.field>

                            <x-ui.field label="Logradouro" for="p-log" required class="sm:col-span-2" :error="$errors->first('logradouro')">
                                <x-ui.input id="p-log" wire:model="form.logradouro" />
                            </x-ui.field>

                            <x-ui.field label="Bairro" for="p-bai" required class="sm:col-span-2" :error="$errors->first('bairro')">
                                <x-ui.input id="p-bai" wire:model="form.bairro" />
                            </x-ui.field>

                            <x-ui.field label="Município" for="p-mun" required class="sm:col-span-2" :error="$errors->first('municipio')">
                                <x-ui.input id="p-mun" wire:model="form.municipio" />
                            </x-ui.field>

                            <x-ui.field label="UF" for="p-uf" required :error="$errors->first('uf')">
                                <x-ui.input id="p-uf" wire:model="form.uf" maxlength="2" />
                            </x-ui.field>

                            <x-ui.field label="Código IBGE" for="p-ibge" required
                                hint="Vem do CEP. Pode digitar."
                                :error="$errors->first('codigo_municipio')">
                                <x-ui.input id="p-ibge" wire:model="form.codigo_municipio" numeric maxlength="7" />
                            </x-ui.field>
                        </div>

                        {{-- Contato --}}
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Telefone" for="p-tel">
                                <x-ui.input id="p-tel" wire:model="form.telefone" />
                            </x-ui.field>

                            <x-ui.field label="E-mail" for="p-mail" :error="$errors->first('email')">
                                <x-ui.input id="p-mail" type="email" wire:model="form.email" />
                            </x-ui.field>

                            <x-ui.field label="Observações" for="p-obs" class="sm:col-span-2" :error="$errors->first('observacoes')"
                                hint="Uso interno. Não sai no CT-e nem na fatura.">
                                <x-ui.textarea id="p-obs" wire:model="form.observacoes" rows="2" maxlength="2000" />
                            </x-ui.field>
                        </div>

                        {{-- Papéis --}}
                        <div>
                            <p class="mb-2 text-[13px] font-semibold text-graphite-800">Papéis</p>
                            <div class="flex flex-wrap gap-5">
                                @foreach ([['e_cliente','Cliente'],['e_fornecedor','Fornecedor'],['e_transportadora','Transportadora']] as [$campo, $rotulo])
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" wire:model.live="form.{{ $campo }}" class="size-4 accent-graphite-900">
                                        {{ $rotulo }}
                                    </label>
                                @endforeach
                            </div>
                            @error('e_cliente')<p class="mt-2 text-xs text-danger-700">{{ $message }}</p>@enderror
                        </div>

                        @if ($form['e_transportadora'])
                            <div class="grid gap-4 rounded-lg bg-graphite-50 p-4 sm:grid-cols-3">
                                <x-ui.field label="Placa" for="p-placa"><x-ui.input id="p-placa" wire:model="form.placa" maxlength="7" /></x-ui.field>
                                <x-ui.field label="UF da placa" for="p-puf"><x-ui.input id="p-puf" wire:model="form.placa_uf" maxlength="2" /></x-ui.field>
                                <x-ui.field label="RNTC" for="p-rntc"><x-ui.input id="p-rntc" wire:model="form.rntc" /></x-ui.field>
                            </div>
                        @endif
                    </fieldset>
                </x-ui.modal-corpo>
                <x-ui.modal-rodape>
                    <x-ui.button variant="secondary" wire:click="fecharFormulario">{{ $podeGerenciar ? 'Cancelar' : 'Fechar' }}</x-ui.button>
                    @if ($podeGerenciar)
                        <x-ui.button type="submit">{{ $editandoId ? 'Salvar alterações' : 'Cadastrar' }}</x-ui.button>
                    @endif
                </x-ui.modal-rodape>
            </form>
        </x-ui.modal>
    @endif
</div>
