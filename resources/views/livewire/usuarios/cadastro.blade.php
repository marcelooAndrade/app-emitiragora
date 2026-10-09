{{-- Padrão de listagem de 09/10/2026: a lista na largura toda, "Novo
     usuário" e o lápis abrem o formulário numa janela, e a lixeira tira o
     acesso a esta empresa. --}}
<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Configuração"
        title="Usuários"
        description="Quem tem acesso a esta empresa, e com qual perfil em cada emitente dela." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif
    @if (session('erro'))
        <x-ui.alert variant="danger">{{ session('erro') }}</x-ui.alert>
    @endif

    <x-ui.card :padded="false">
        <x-ui.barra-lista placeholder="Buscar nome ou e-mail">
            <x-slot:acao>
                <x-ui.botao-novo wire:click="novoUsuario">Novo usuário</x-ui.botao-novo>
            </x-slot:acao>
        </x-ui.barra-lista>

        @if ($this->listados->isEmpty())
            <div class="px-6 pb-6">
                <x-ui.empty-state :title="$busca !== '' ? 'Nenhum usuário encontrado' : 'Nenhum usuário ainda'"
                    :description="$busca !== '' ? 'Confira o nome ou o e-mail digitado.' : 'Cadastre o primeiro pelo botão Novo usuário.'" />
            </div>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-y border-graphite-100 text-left">
                        <th class="px-6">Nome</th>
                        <th class="px-6">E-mail</th>
                        <th class="px-6">Situação</th>
                        <th class="px-6 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->listados as $usuario)
                        <tr wire:key="usuario-{{ $usuario->id }}">
                            <td class="px-6">
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :nome="$usuario->name" />
                                    <span class="font-semibold text-graphite-900">{{ $usuario->name }}@if ($usuario->id === auth()->id())<span class="ml-1.5 text-xs font-medium text-graphite-500">(você)</span>@endif</span>
                                </div>
                            </td>
                            <td class="px-6 text-graphite-700">{{ $usuario->email }}</td>
                            <td class="px-6"><x-ui.pastilha :tom="$usuario->ativo ? 'sucesso' : 'neutro'">{{ $usuario->ativo ? 'Ativo' : 'Inativo' }}</x-ui.pastilha></td>
                            <td class="px-6 text-right whitespace-nowrap">
                                <x-ui.botao-icone acao="editar" rotulo="Editar {{ $usuario->name }}" wire:click="editar({{ $usuario->id }})" />
                                @if ($usuario->id !== auth()->id())
                                    @if ($usuario->ativo)
                                        <x-ui.botao-icone acao="excluir" rotulo="Remover o acesso de {{ $usuario->name }}" wire:click="removerAcesso({{ $usuario->id }})"
                                            wire:confirm="Remover o acesso de {{ $usuario->name }} a esta empresa? A conta não é apagada: se a pessoa tiver acesso a outra empresa, continua lá." />
                                    @else
                                        <x-ui.botao-icone acao="reativar" rotulo="Reativar {{ $usuario->name }}" wire:click="reativar({{ $usuario->id }})" />
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
        <x-ui.modal :titulo="$editandoId && $emailVerificado ? 'Editar usuário' : 'Novo usuário'" largura="xl">
            <form wire:submit="{{ $emailVerificado ? 'salvar' : 'verificarEmail' }}">
                <x-ui.modal-corpo class="grid gap-4">
                    <x-ui.field label="E-mail" for="u-email" required :error="$errors->first('form.email')"
                        :hint="$emailVerificado ? null : 'Verificar diz se a pessoa já tem conta em outra empresa: aí só o acesso a esta é criado.'">
                        <div class="flex gap-2">
                            <x-ui.input id="u-email" type="email" wire:model="form.email" maxlength="254" class="min-w-0 flex-1" :disabled="$emailVerificado && $contaExistente" />
                            @unless ($emailVerificado)
                                <x-ui.button variant="secondary" wire:click="verificarEmail" wire:loading.attr="disabled" wire:target="verificarEmail" class="shrink-0">
                                    <span wire:loading.remove wire:target="verificarEmail">Verificar</span>
                                    <span wire:loading wire:target="verificarEmail">Verificando...</span>
                                </x-ui.button>
                            @endunless
                        </div>
                    </x-ui.field>

                    @if ($emailVerificado)
                        @if ($contaExistente)
                            <x-ui.alert variant="info">
                                Conta de {{ $form['name'] }}. Aqui muda só o acesso a esta empresa; nome e senha são da própria pessoa.
                            </x-ui.alert>
                        @else
                            <x-ui.field label="Nome" for="u-nome" required :error="$errors->first('form.name')">
                                <x-ui.input id="u-nome" wire:model="form.name" maxlength="160" />
                            </x-ui.field>
                            <x-ui.field label="Senha inicial" for="u-senha" required :error="$errors->first('form.senha')" hint="A pessoa troca depois, em Configurações da conta.">
                                <x-ui.input id="u-senha" type="password" wire:model="form.senha" minlength="8" />
                            </x-ui.field>
                        @endif

                        <div class="grid gap-3 rounded-lg bg-graphite-50 p-4">
                            <p class="text-[13px] font-semibold text-graphite-800">Perfil por emitente</p>
                            @error('papeis') <p class="text-xs font-medium text-danger-700">{{ $message }}</p> @enderror
                            @foreach ($this->emitentesDaEmpresa as $emitenteDaEmpresa)
                                <x-ui.field :label="$emitenteDaEmpresa->nome_fantasia ?: $emitenteDaEmpresa->razao_social" :for="'u-papel-'.$emitenteDaEmpresa->id">
                                    <x-ui.select :id="'u-papel-'.$emitenteDaEmpresa->id" wire:model="papeis.{{ $emitenteDaEmpresa->id }}">
                                        <option value="">Sem acesso</option>
                                        @foreach (\App\Enums\Perfil::cases() as $perfil)
                                            <option value="{{ $perfil->value }}">{{ $perfil->value }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </x-ui.field>
                            @endforeach
                        </div>
                    @endif
                </x-ui.modal-corpo>
                <x-ui.modal-rodape>
                    <x-ui.button variant="secondary" wire:click="fecharFormulario">Cancelar</x-ui.button>
                    @if ($emailVerificado)
                        <x-ui.button type="submit">{{ $editandoId ? 'Salvar alterações' : 'Cadastrar usuário' }}</x-ui.button>
                    @endif
                </x-ui.modal-rodape>
            </form>
        </x-ui.modal>
    @endif

</div>
