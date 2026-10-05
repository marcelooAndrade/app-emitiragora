{{-- Lado a lado, não empilhado: listagem numa coluna estreita (30% da
     largura), formulário numa coluna larga (70%), as duas ocupando a
     altura toda da tela. Cabe tudo numa tela só, sem rolar a página; só a
     listagem rola por dentro dela mesma, nas linhas da tabela. O formulário
     tem rolagem própria como reforço, para nunca ficar inalcançável numa
     tela baixa, mas em qualquer tela normal ele cabe inteiro sem rolar.

     Só a partir de `lg`: uma coluna de 30% de largura num celular vira uma
     fatia inútil. Sem `lg:`, o celular empilha listagem e formulário em
     largura cheia, cada um no tamanho natural, com a página inteira
     rolando quando for preciso — o mesmo que toda outra tela já faz. --}}
<div class="flex flex-col gap-4 lg:h-full lg:min-h-0">

    <x-ui.page-header
        eyebrow="Cadastros"
        :title="$editandoId ? 'Editar cadastro' : 'Destinatários e parceiros'"
        description="Cliente, fornecedor e transportadora ficam no mesmo cadastro. A mesma empresa pode ter mais de um papel."
        class="shrink-0">
        <x-slot:actions>
            @if ($editandoId)
                <x-ui.button variant="ghost" wire:click="limparFormulario">Cancelar edição</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('sucesso'))
        <x-ui.alert variant="success" class="shrink-0">{{ session('sucesso') }}</x-ui.alert>
    @endif

    @if ($avisoSituacao)
        <x-ui.alert variant="warning" class="shrink-0" title="Situação cadastral: {{ $avisoSituacao }}">
            A Receita informa que esta empresa não está ativa. O cadastro é permitido, mas confira antes de emitir.
        </x-ui.alert>
    @endif

    {{-- Linha com as duas colunas. Em pé (mobile) ela nem existe como
         "linha": as classes de `lg:` é que a tornam uma linha; sem elas, os
         dois cards abaixo simplesmente se empilham na ordem em que aparecem. --}}
    <div class="flex flex-col gap-6 lg:min-h-0 lg:flex-1 lg:flex-row">

        {{-- Listagem: a coluna estreita. --}}
        <x-ui.card title="Cadastrados" :padded="false" class="lg:flex lg:w-[30%] lg:shrink-0 lg:min-h-0 lg:flex-col">
            <x-slot:actions>
                <x-ui.select wire:model.live="papel" class="w-auto">
                    <option value="todos">Todos os papéis</option>
                    <option value="clientes">Clientes</option>
                    <option value="fornecedores">Fornecedores</option>
                    <option value="transportadoras">Transportadoras</option>
                </x-ui.select>
                <x-ui.input wire:model.live.debounce.400ms="busca" placeholder="Buscar nome ou documento" class="w-56" />
            </x-slot:actions>

            @if ($this->pessoas->isEmpty())
                <x-ui.empty-state class="m-5" title="Nenhum cadastro" description="Use o formulário ao lado para incluir o primeiro." />
            @else
                <div class="flex h-full min-h-0 min-w-0 flex-col">
                    {{-- Lista compacta, não tabela: a coluna é estreita (30% da
                         largura), e uma tabela de várias colunas não cabe sem
                         rolar na horizontal. Documento e município continuam
                         no formulário ao editar; papéis viram filtro, não
                         mais coluna. --}}
                    <div class="min-h-0 min-w-0 flex-1 overflow-y-auto">
                        <ul class="divide-y divide-graphite-100">
                            @foreach ($this->pessoas as $pessoa)
                                <li wire:key="pessoa-{{ $pessoa->id }}" class="flex min-w-0 items-center gap-3 px-3 py-2.5">
                                    <flux:avatar :name="$pessoa->nome_fantasia ?: $pessoa->razao_social" size="sm" circle />

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-graphite-900">{{ $pessoa->nome_fantasia ?: $pessoa->razao_social }}</p>
                                        <p class="num truncate text-xs text-graphite-500">{{ $pessoa->documentoFormatado() }}</p>
                                    </div>

                                    <x-ui.button
                                        variant="ghost" size="sm"
                                        wire:click="editar({{ $pessoa->id }})"
                                        aria-label="Editar {{ $pessoa->nome_fantasia ?: $pessoa->razao_social }}">
                                        <flux:icon.pencil-square class="size-4" />
                                    </x-ui.button>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="shrink-0 border-t border-graphite-200/70 p-3">{{ $this->pessoas->links() }}</div>
                </div>
            @endif
        </x-ui.card>

        {{-- Formulário: a coluna larga. --}}
        @can('pessoa.gerenciar')
        <x-ui.card :title="$editandoId ? 'Editando' : 'Novo cadastro'" class="lg:min-h-0 lg:flex-1 lg:overflow-y-auto">
        <form wire:submit="salvar" class="grid gap-5">

            {{-- Identificação --}}
            <div class="grid gap-4 sm:grid-cols-3">
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
                    :hint="$form['tipo_pessoa'] === 'J' ? 'Aceita o formato alfanumérico da Receita.' : null"
                    :error="$errors->first('documento')">
                    <div class="flex gap-2">
                        <x-ui.input id="p-doc" wire:model="form.documento" numeric class="flex-1" />
                        @if ($form['tipo_pessoa'] === 'J')
                            <x-ui.button variant="secondary" size="md" wire:click="buscarCnpj" wire:loading.attr="disabled" wire:target="buscarCnpj">
                                <span wire:loading.remove wire:target="buscarCnpj">Buscar</span>
                                <span wire:loading wire:target="buscarCnpj">...</span>
                            </x-ui.button>
                        @endif
                    </div>
                </x-ui.field>

                <x-ui.field label="Nome fantasia" for="p-fant">
                    <x-ui.input id="p-fant" wire:model="form.nome_fantasia" />
                </x-ui.field>
            </div>

            <x-ui.field
                :label="$form['tipo_pessoa'] === 'F' ? 'Nome' : 'Razão social'"
                for="p-razao" required :error="$errors->first('razao_social')">
                <x-ui.input id="p-razao" wire:model="form.razao_social" />
            </x-ui.field>

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
                <x-ui.field label="CEP" for="p-cep" required :error="$errors->first('cep')">
                    <div class="flex gap-2">
                        <x-ui.input id="p-cep" wire:model="form.cep" numeric class="flex-1" />
                        <x-ui.button variant="secondary" size="md" wire:click="buscarCep" wire:loading.attr="disabled" wire:target="buscarCep">
                            <span wire:loading.remove wire:target="buscarCep">CEP</span>
                            <span wire:loading wire:target="buscarCep">...</span>
                        </x-ui.button>
                    </div>
                </x-ui.field>

                <x-ui.field label="Logradouro" for="p-log" required class="sm:col-span-2" :error="$errors->first('logradouro')">
                    <x-ui.input id="p-log" wire:model="form.logradouro" />
                </x-ui.field>

                <x-ui.field label="Número" for="p-num" required :error="$errors->first('numero')">
                    <x-ui.input id="p-num" wire:model="form.numero" />
                </x-ui.field>

                <x-ui.field label="Complemento" for="p-comp">
                    <x-ui.input id="p-comp" wire:model="form.complemento" />
                </x-ui.field>

                <x-ui.field label="Bairro" for="p-bai" required :error="$errors->first('bairro')">
                    <x-ui.input id="p-bai" wire:model="form.bairro" />
                </x-ui.field>

                <x-ui.field label="Município" for="p-mun" required :error="$errors->first('municipio')">
                    <x-ui.input id="p-mun" wire:model="form.municipio" />
                </x-ui.field>

                <x-ui.field label="UF" for="p-uf" required :error="$errors->first('uf')">
                    <x-ui.input id="p-uf" wire:model="form.uf" maxlength="2" />
                </x-ui.field>

                <x-ui.field label="Código IBGE" for="p-ibge" required
                    hint="Obrigatório no CT-e. Vem do CEP, ou do município e da UF. Pode digitar."
                    :error="$errors->first('codigo_municipio')">
                    <x-ui.input id="p-ibge" wire:model="form.codigo_municipio" numeric maxlength="7" />
                </x-ui.field>

                <x-ui.field label="Telefone" for="p-tel">
                    <x-ui.input id="p-tel" wire:model="form.telefone" />
                </x-ui.field>

                <x-ui.field label="E-mail" for="p-mail" class="sm:col-span-2" :error="$errors->first('email')">
                    <x-ui.input id="p-mail" type="email" wire:model="form.email" />
                </x-ui.field>

                <x-ui.field label="Observações" for="p-obs" class="sm:col-span-2" :error="$errors->first('observacoes')"
                    hint="Uso interno. Não sai no CT-e nem na fatura.">
                    <x-ui.textarea id="p-obs" wire:model="form.observacoes" rows="3" maxlength="2000" />
                </x-ui.field>
            </div>

            {{-- Papéis --}}
            <div>
                <p class="etiqueta mb-2 text-graphite-500">Papéis</p>
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
                <div class="grid gap-4 border-l-2 border-graphite-200 pl-4 sm:grid-cols-3">
                    <x-ui.field label="Placa" for="p-placa"><x-ui.input id="p-placa" wire:model="form.placa" maxlength="7" /></x-ui.field>
                    <x-ui.field label="UF da placa" for="p-puf"><x-ui.input id="p-puf" wire:model="form.placa_uf" maxlength="2" /></x-ui.field>
                    <x-ui.field label="RNTC" for="p-rntc"><x-ui.input id="p-rntc" wire:model="form.rntc" /></x-ui.field>
                </div>
            @endif

            <div class="flex gap-2">
                <x-ui.button type="submit" size="lg">{{ $editandoId ? 'Salvar alterações' : 'Cadastrar' }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
    @endcan
    </div>
</div>
