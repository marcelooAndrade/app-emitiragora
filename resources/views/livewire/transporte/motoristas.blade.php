<div class="grid gap-6">
    <x-ui.page-header eyebrow="Transporte" title="Motoristas" description="Quem dirige. Nome e CPF vão no MDF-e." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <x-ui.card :padded="false">
            @if ($this->motoristas->isEmpty())
                <div class="p-5"><x-ui.empty-state title="Nenhum motorista" description="Cadastre ao lado, ou direto na viagem." /></div>
            @else
                <x-ui.table>
                    <thead>
                        <tr class="border-b border-graphite-200 text-left">
                            <th class="etiqueta px-5 py-2 text-graphite-500">Nome</th>
                            <th class="etiqueta px-5 py-2 text-graphite-500">CPF</th>
                            <th class="etiqueta px-5 py-2 text-graphite-500">Telefone</th>
                            <th class="px-5 py-2"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->motoristas as $m)
                            <tr class="border-b border-graphite-100 last:border-0 {{ $m->ativo ? '' : 'opacity-50' }}" wire:key="m-{{ $m->id }}">
                                <td class="px-5 py-3 font-semibold text-graphite-900">{{ $m->nome }}</td>
                                <td class="num px-5 py-3 text-graphite-700">{{ $m->cpfFormatado() }}</td>
                                <td class="px-5 py-3 text-graphite-700">{{ $m->telefone ?: '—' }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    @can('transporte.operar')
                                        <x-ui.button variant="ghost" size="sm" wire:click="editar({{ $m->id }})">Editar</x-ui.button>
                                        <x-ui.button variant="ghost" size="sm" wire:click="alternarAtivo({{ $m->id }})">{{ $m->ativo ? 'Desativar' : 'Reativar' }}</x-ui.button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>

        @can('transporte.operar')
            <x-ui.card :title="$editandoId ? 'Editar motorista' : 'Novo motorista'">
                <form wire:submit="salvar" class="grid gap-3">
                    <x-ui.field label="Nome" required :error="$errors->first('nome')">
                        <x-ui.input wire:model="nome" maxlength="60" />
                    </x-ui.field>
                    <x-ui.field label="CPF" required :error="$errors->first('cpf')">
                        <x-ui.input wire:model="cpf" inputmode="numeric" maxlength="14" />
                    </x-ui.field>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.field label="CNH" :error="$errors->first('cnh')">
                            <x-ui.input wire:model="cnh" maxlength="20" />
                        </x-ui.field>
                        <x-ui.field label="Telefone" :error="$errors->first('telefone')">
                            <x-ui.input wire:model="telefone" maxlength="20" />
                        </x-ui.field>
                    </div>
                    <x-ui.field label="Chave Pix" hint="Para pagar o frete do motorista terceiro." :error="$errors->first('chavePix')">
                        <x-ui.input wire:model="chavePix" maxlength="77" />
                    </x-ui.field>
                    <div class="flex gap-2">
                        <x-ui.button type="submit">{{ $editandoId ? 'Salvar' : 'Cadastrar' }}</x-ui.button>
                        @if ($editandoId)
                            <x-ui.button variant="ghost" wire:click="novo">Cancelar</x-ui.button>
                        @endif
                    </div>
                </form>
            </x-ui.card>
        @endcan
    </div>
</div>
