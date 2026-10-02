<div class="grid gap-6">
    <x-ui.page-header eyebrow="Transporte" title="Veículos"
        description="Cavalos e carretas que vão no MDF-e. Proprietário só quando o veículo é de terceiro." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
        <x-ui.card :padded="false">
            @if ($this->veiculos->isEmpty())
                <div class="p-5"><x-ui.empty-state title="Nenhum veículo" description="Cadastre ao lado, ou direto na viagem." /></div>
            @else
                <x-ui.table>
                    <thead>
                        <tr class="border-b border-graphite-200 text-left">
                            <th class="etiqueta px-5 py-2 text-graphite-500">Placa</th>
                            <th class="etiqueta px-5 py-2 text-graphite-500">Tipo</th>
                            <th class="etiqueta px-5 py-2 text-graphite-500">Proprietário</th>
                            <th class="etiqueta px-5 py-2 text-graphite-500">Para o MDF-e</th>
                            <th class="px-5 py-2"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->veiculos as $v)
                            @php $faltando = $v->pendenciasMdfe(); @endphp
                            <tr class="border-b border-graphite-100 last:border-0 {{ $v->ativo ? '' : 'opacity-50' }}" wire:key="v-{{ $v->id }}">
                                <td class="whitespace-nowrap px-5 py-3 font-semibold text-graphite-900">{{ $v->placaFormatada() }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-graphite-700">{{ $v->eTracao() ? 'Cavalo' : 'Carreta' }}<span class="block text-xs text-graphite-500">tara {{ number_format($v->tara_kg, 0, ',', '.') }} kg</span></td>
                                <td class="px-5 py-3 text-graphite-700">{{ $v->deTerceiro() ? $v->proprietario_nome : 'Próprio' }}</td>
                                <td class="min-w-56 px-5 py-3 text-xs">
                                    @if ($faltando)
                                        <span class="font-semibold text-danger-700">Incompleto</span>
                                        <span class="block text-graphite-500">falta {{ implode(', ', $faltando) }}</span>
                                    @else
                                        <span class="text-success-700">Completo</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    @can('transporte.operar')
                                        <x-ui.button variant="ghost" size="sm" wire:click="editar({{ $v->id }})">Editar</x-ui.button>
                                        <x-ui.button variant="ghost" size="sm" wire:click="alternarAtivo({{ $v->id }})">{{ $v->ativo ? 'Desativar' : 'Reativar' }}</x-ui.button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>

        @can('transporte.operar')
            <x-ui.card :title="$editandoId ? 'Editar veículo' : 'Novo veículo'">
                <form wire:submit="salvar" class="grid gap-3">
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
                        @if ($this->usaEfrete)
                            <x-ui.field label="Chassi" hint="17 caracteres. Para o CIOT." :error="$errors->first('chassi')">
                                <x-ui.input wire:model="chassi" maxlength="17" />
                            </x-ui.field>
                            <x-ui.field label="Eixos" hint="Para o CIOT." :error="$errors->first('eixos')">
                                <x-ui.input wire:model="eixos" inputmode="numeric" maxlength="2" />
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
