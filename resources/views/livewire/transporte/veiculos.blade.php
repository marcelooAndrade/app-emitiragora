<div class="grid gap-6">
    <x-ui.page-header eyebrow="Transporte" title="Veículos"
        description="Cavalos e carretas que vão no MDF-e. Proprietário só quando o veículo é de terceiro." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    {{-- Listagem na largura toda; cadastrar e editar abrem a janela abaixo. --}}
    <x-ui.card :padded="false">
        <x-ui.barra-lista placeholder="Buscar por placa ou proprietário">
            @can('transporte.operar')
                <x-slot:acao>
                    <x-ui.botao-novo wire:click="novo">Novo veículo</x-ui.botao-novo>
                </x-slot:acao>
            @endcan
        </x-ui.barra-lista>

        @if ($this->listados->isEmpty())
            <div class="px-6 pb-6">
                <x-ui.empty-state :title="$busca !== '' ? 'Nenhum veículo encontrado' : 'Nenhum veículo'"
                    :description="$busca !== '' ? 'Confira a placa ou o nome digitado.' : 'Cadastre aqui, ou direto na viagem.'" />
            </div>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-y border-graphite-100 text-left">
                        <th class="px-6">Placa</th>
                        <th class="px-6">Tipo</th>
                        <th class="px-6">Eixos</th>
                        <th class="px-6">Proprietário</th>
                        <th class="px-6">Para o MDF-e</th>
                        <th class="px-6">Situação</th>
                        <th class="px-6 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->listados as $v)
                        @php $faltando = $v->pendenciasMdfe(); @endphp
                        <tr wire:key="v-{{ $v->id }}">
                            <td class="whitespace-nowrap px-6">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-graphite-900/[0.06] text-graphite-700" aria-hidden="true">
                                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>
                                    </span>
                                    <span class="num font-semibold text-graphite-900">{{ $v->placaFormatada() }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 text-graphite-700">{{ $v->eTracao() ? 'Cavalo' : 'Carreta' }}<span class="block text-xs text-graphite-500">tara {{ number_format($v->tara_kg, 0, ',', '.') }} kg</span></td>
                            <td class="num px-6 text-graphite-700">{{ $v->eixos ?: '—' }}</td>
                            <td class="px-6 text-graphite-700">{{ $v->deTerceiro() ? $v->proprietario_nome : 'Da empresa' }}</td>
                            <td class="min-w-48 px-6 text-xs">
                                @if ($faltando)
                                    <span class="font-semibold text-danger-700">Incompleto</span>
                                    <span class="block text-graphite-500">falta {{ implode(', ', $faltando) }}</span>
                                @else
                                    <span class="font-semibold text-success-700">Completo</span>
                                @endif
                            </td>
                            <td class="px-6"><x-ui.pastilha :tom="$v->ativo ? 'sucesso' : 'neutro'">{{ $v->ativo ? 'Ativo' : 'Inativo' }}</x-ui.pastilha></td>
                            <td class="px-6 text-right whitespace-nowrap">
                                @can('transporte.operar')
                                    <x-ui.botao-icone acao="editar" rotulo="Editar {{ $v->placaFormatada() }}" wire:click="editar({{ $v->id }})" />
                                    @if ($v->ativo)
                                        <x-ui.botao-icone acao="excluir" rotulo="Excluir {{ $v->placaFormatada() }}" wire:click="excluir({{ $v->id }})"
                                            wire:confirm="Excluir o veículo {{ $v->placaFormatada() }}? Se ele já estiver em alguma viagem, só é inativado." />
                                    @else
                                        <x-ui.botao-icone acao="reativar" rotulo="Reativar {{ $v->placaFormatada() }}" wire:click="alternarAtivo({{ $v->id }})" />
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
        <x-ui.modal :titulo="$editandoId ? 'Editar veículo' : 'Novo veículo'" largura="3xl">
            <form wire:submit="salvar">
                <x-ui.modal-corpo class="grid gap-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.field label="Tipo" required>
                        <x-ui.select wire:model.live="tipo">
                            <option value="tracao">Cavalo (tração)</option>
                            <option value="reboque">Carreta (reboque)</option>
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field label="Placa" required :error="$errors->first('placa')">
                        <x-ui.input wire:model="placa" maxlength="8" placeholder="ABC1D23" />
                    </x-ui.field>
                    <x-ui.field label="RENAVAM" :error="$errors->first('renavam')">
                        <x-ui.input wire:model="renavam" inputmode="numeric" maxlength="11" />
                    </x-ui.field>
                    <x-ui.field label="UF do licenciamento" required :error="$errors->first('uf')">
                        <x-ui.input wire:model="uf" maxlength="2" />
                    </x-ui.field>
                    <x-ui.field label="Tara (kg)" required :error="$errors->first('tara')">
                        <x-ui.input wire:model="tara" inputmode="numeric" />
                    </x-ui.field>
                    <x-ui.field label="Capacidade (kg)" :error="$errors->first('capacidade')">
                        <x-ui.input wire:model="capacidade" inputmode="numeric" />
                    </x-ui.field>
                    @if ($tipo === 'tracao')
                        <x-ui.field label="Tipo de rodado" required :error="$errors->first('rodado')">
                            <x-ui.select wire:model="rodado">
                                @foreach (App\Models\Veiculo::TIPOS_RODADO as $codigo => $nome)
                                    <option value="{{ $codigo }}">{{ $nome }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                    @endif
                    <x-ui.field label="Carroceria" required :error="$errors->first('carroceria')">
                        <x-ui.select wire:model="carroceria">
                            @foreach (App\Models\Veiculo::TIPOS_CARROCERIA as $codigo => $nome)
                                <option value="{{ $codigo }}">{{ $nome }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    {{-- CIOT para todos (DF-026): a ANTT pede os eixos de todo veículo,
                         de 2 a 4 no cavalo ou caminhão e de 1 a 4 na carreta. --}}
                    <x-ui.field label="Eixos" hint="Para o CIOT: 2 a 4 no cavalo ou caminhão, 1 a 4 na carreta." :error="$errors->first('eixos')">
                        <x-ui.input wire:model="eixos" inputmode="numeric" maxlength="1" />
                    </x-ui.field>
                    @if ($this->usaEfrete)
                        <x-ui.field label="Chassi" hint="17 caracteres. Para o CIOT pelo e-Frete." :error="$errors->first('chassi')">
                            <x-ui.input wire:model="chassi" maxlength="17" />
                        </x-ui.field>
                    @endif
                </div>

                <x-ui.field label="De quem é" required>
                    <x-ui.select wire:model.live="proprietarioTipo">
                        <option value="proprio">Da empresa</option>
                        <option value="terceiro">De terceiro (agregado ou autônomo)</option>
                    </x-ui.select>
                </x-ui.field>

                @if ($proprietarioTipo === 'terceiro')
                    <div class="grid gap-3 rounded-lg border border-graphite-200 bg-graphite-50 p-4 sm:grid-cols-2">
                        <x-ui.field label="CPF ou CNPJ do proprietário" required :error="$errors->first('proprietarioDocumento')">
                            <x-ui.input wire:model="proprietarioDocumento" inputmode="numeric" />
                        </x-ui.field>
                        <x-ui.field label="Nome" required :error="$errors->first('proprietarioNome')">
                            <x-ui.input wire:model="proprietarioNome" maxlength="60" />
                        </x-ui.field>
                        <x-ui.field label="RNTRC (8 dígitos)" required :error="$errors->first('proprietarioRntrc')">
                            <x-ui.input wire:model="proprietarioRntrc" inputmode="numeric" maxlength="8" />
                        </x-ui.field>
                        <x-ui.field label="Tipo" required :error="$errors->first('proprietarioTp')">
                            <x-ui.select wire:model="proprietarioTp">
                                @foreach (App\Models\Veiculo::TIPOS_PROPRIETARIO as $codigo => $nome)
                                    <option value="{{ $codigo }}">{{ $nome }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field label="Inscrição estadual" :error="$errors->first('proprietarioIe')">
                            <x-ui.input wire:model="proprietarioIe" />
                        </x-ui.field>
                        <x-ui.field label="UF do proprietário" :error="$errors->first('proprietarioUf')">
                            <x-ui.input wire:model="proprietarioUf" maxlength="2" />
                        </x-ui.field>
                        @if ($this->usaEfrete)
                            <p class="text-xs font-semibold text-graphite-700 sm:col-span-2">Endereço do proprietário, para o CIOT</p>
                            <x-ui.field label="CEP" :hint="$proprietarioMunicipio ?: 'Preenche o endereço sozinho.'" :error="$errors->first('proprietarioCep')">
                                <x-ui.input wire:model.blur="proprietarioCep" inputmode="numeric" maxlength="9" />
                            </x-ui.field>
                            <x-ui.field label="Número" :error="$errors->first('proprietarioNumero')">
                                <x-ui.input wire:model="proprietarioNumero" maxlength="10" />
                            </x-ui.field>
                            <x-ui.field label="Rua" :error="$errors->first('proprietarioLogradouro')">
                                <x-ui.input wire:model="proprietarioLogradouro" maxlength="60" />
                            </x-ui.field>
                            <x-ui.field label="Bairro" :error="$errors->first('proprietarioBairro')">
                                <x-ui.input wire:model="proprietarioBairro" maxlength="60" />
                            </x-ui.field>
                        @endif
                    </div>
                @endif
                </x-ui.modal-corpo>
                <x-ui.modal-rodape>
                    <x-ui.button variant="secondary" wire:click="fecharFormulario">Cancelar</x-ui.button>
                    <x-ui.button type="submit">{{ $editandoId ? 'Salvar alterações' : 'Cadastrar veículo' }}</x-ui.button>
                </x-ui.modal-rodape>
            </form>
        </x-ui.modal>
    @endif
</div>
