@php
    use App\Enums\Transporte\CteStatus;
    use App\Enums\Transporte\MdfeStatus;
    use App\Support\Dinheiro;

    $viagem = $this->viagem;
    $travada = $viagem->travada();
    $mdfe = $viagem->mdfe;
    $mdfeEmitido = in_array($mdfe?->status, [MdfeStatus::Autorizado, MdfeStatus::Encerrado], true);
    $ctesValidos = $viagem->ctes->reject(fn ($c) => $c->status === CteStatus::Cancelado);
    $todosAutorizados = $ctesValidos->isNotEmpty() && $ctesValidos->every(fn ($c) => $c->status === CteStatus::Autorizado);
    $aFaturar = $viagem->ctes->filter(fn ($c) => $c->status === CteStatus::Autorizado && $c->fatura_id === null);
    $passos = [
        ['Notas da carga', $viagem->notas->isNotEmpty()],
        ['Motorista, veículo e frete', $viagem->motorista_id && $viagem->veiculo_id && $this->freteTotal > 0],
        ['CT-e e MDF-e', $mdfeEmitido],
    ];
@endphp

<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Transporte"
        :title="'Viagem '.$viagem->numeroFormatado()"
        :description="'Carregamento em '.$viagem->data_carregamento?->format('d/m/Y').' · '.$viagem->notas->count().' NF-e · '.number_format($viagem->pesoTotalKg(), 0, ',', '.').' kg'">
        <x-slot:actions>
            <x-ui.badge-status :status="$viagem->status" />
            <x-ui.button variant="secondary" :href="route('viagens')" wire:navigate>Voltar</x-ui.button>
            @can('transporte.operar')
                @if (! $mdfeEmitido && $viagem->notas->isNotEmpty())
                    <x-ui.button wire:click="emitir" wire:loading.attr="disabled" wire:target="emitir">
                        <span wire:loading.remove wire:target="emitir">{{ $travada ? 'Continuar emissão' : 'Emitir CT-e e MDF-e' }}</span>
                        <span wire:loading wire:target="emitir">Falando com a SEFAZ...</span>
                    </x-ui.button>
                @endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Os três passos são uma sequência de verdade: cada um depende do anterior. --}}
    <ol class="grid gap-2 sm:grid-cols-3">
        @foreach ($passos as $i => [$rotulo, $feito])
            <li @class([
                'flex items-center gap-3 rounded-lg border px-4 py-3',
                'border-success-300 bg-success-50' => $feito,
                'border-graphite-200 bg-white' => ! $feito,
            ])>
                <span @class([
                    'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                    'bg-success-600 text-white' => $feito,
                    'bg-graphite-100 text-graphite-600' => ! $feito,
                ])>{{ $feito ? '✓' : $i + 1 }}</span>
                <span class="text-sm font-semibold text-graphite-800">{{ $rotulo }}</span>
            </li>
        @endforeach
    </ol>

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    @if ($resultado)
        @if ($resultado['erros'])
            <x-ui.alert variant="danger" title="A emissão parou. Corrija e aperte de novo.">
                <ul class="grid gap-1.5">
                    @foreach ($resultado['erros'] as $erro)
                        <li class="whitespace-pre-line">{{ $erro }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif
        @if ($resultado['ok'])
            <x-ui.alert variant="success" :title="$resultado['erros'] ? 'Autorizados até aqui' : 'Tudo autorizado. Boa viagem.'">
                {{ implode(' ', $resultado['ok']) }}
            </x-ui.alert>
        @endif
    @endif

    @foreach (['viagem', 'documentos', 'faturamento', 'encerramento'] as $campoErro)
        @error($campoErro)
            <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
        @enderror
    @endforeach

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="grid min-w-0 content-start gap-6">

            {{-- 1. Notas --}}
            <x-ui.card title="1. Notas da carga" subtitle="Solte os XML das NF-e que vão no caminhão. O CT-e sai delas, agrupado por remetente e destinatário." :padded="false">
                @if (! $travada)
                    @can('transporte.operar')
                        <div class="border-b border-graphite-100 p-5">
                            <label for="vg-xml" class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-graphite-300 bg-graphite-50 px-4 py-6 text-center transition-colors hover:border-graphite-500">
                                <span class="text-sm font-semibold text-graphite-800">
                                    <span wire:loading.remove wire:target="arquivos">Escolher XML das NF-e</span>
                                    <span wire:loading wire:target="arquivos">Lendo as notas...</span>
                                </span>
                                <span class="text-xs text-graphite-500">Vários de uma vez. Só NF-e autorizada (com protocolo).</span>
                                <input id="vg-xml" type="file" wire:model="arquivos" multiple accept=".xml" class="sr-only">
                            </label>
                            @error('arquivos.*') <p class="mt-2 text-xs text-danger-700">{{ $message }}</p> @enderror
                            @foreach ($falhas as $falha)
                                <x-ui.alert variant="warning" :title="$falha['arquivo']" class="mt-3">{{ $falha['erro'] }}</x-ui.alert>
                            @endforeach
                        </div>
                    @endcan
                @endif

                @if ($viagem->notas->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state title="Nenhuma NF-e ainda" description="Comece pelos XML das notas da carga. Remetente, destinatário, peso e valor vêm deles." />
                    </div>
                @else
                    <x-ui.table>
                        <thead>
                            <tr class="border-b border-graphite-200 text-left">
                                <th class="etiqueta px-5 py-2 text-graphite-500">NF-e</th>
                                <th class="etiqueta px-5 py-2 text-graphite-500">De → Para</th>
                                <th class="etiqueta px-5 py-2 text-right text-graphite-500">Peso (kg)</th>
                                <th class="etiqueta px-5 py-2 text-right text-graphite-500">Valor</th>
                                <th class="px-5 py-2"><span class="sr-only">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($viagem->notas as $nota)
                                <tr class="border-b border-graphite-100 last:border-0" wire:key="nota-{{ $nota->id }}">
                                    <td class="px-5 py-3">
                                        <p class="font-semibold text-graphite-900">{{ $nota->numero }}</p>
                                        <p class="text-xs text-graphite-500">{{ $nota->produto ?: 'Sem descrição' }}</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        <p class="text-graphite-800">{{ $nota->remetente['nome'] }}</p>
                                        <p class="text-xs text-graphite-500">{{ $nota->remetente['municipio'] }}/{{ $nota->uf_origem }} → {{ $nota->destinatario['nome'] }}, {{ $nota->destinatario['municipio'] }}/{{ $nota->uf_destino }}</p>
                                    </td>
                                    <td class="num px-5 py-3 text-right">
                                        @if ($travada)
                                            {{ rtrim(rtrim(number_format((float) $nota->peso_kg, 3, ',', '.'), '0'), ',') }}
                                        @else
                                            <input type="text" inputmode="decimal" wire:model="pesos.{{ $nota->id }}" wire:change="salvarPeso({{ $nota->id }})"
                                                aria-label="Peso da NF-e {{ $nota->numero }}"
                                                @class(['num w-28 rounded-md border px-2 py-1 text-right text-sm', 'border-danger-400 bg-danger-50' => (float) $nota->peso_kg <= 0, 'border-graphite-300' => (float) $nota->peso_kg > 0])>
                                        @endif
                                    </td>
                                    <td class="num whitespace-nowrap px-5 py-3 text-right text-graphite-800">R$ {{ Dinheiro::formatar($nota->valor_centavos) }}</td>
                                    <td class="px-5 py-3 text-right">
                                        @if (! $travada)
                                            @can('transporte.operar')
                                                <x-ui.button variant="ghost" size="sm" wire:click="removerNota({{ $nota->id }})" wire:confirm="Tirar a NF-e {{ $nota->numero }} da viagem?">Tirar</x-ui.button>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                @endif
            </x-ui.card>

            {{-- 2. Viagem --}}
            <x-ui.card title="2. Motorista, veículo e frete" subtitle="O frete é rateado entre os CT-e pelo peso de cada um.">
                <form wire:submit="salvarViagem" class="grid gap-5">
                    <fieldset @disabled($mdfeEmitido || ! auth()->user()->can('transporte.operar')) class="grid gap-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Motorista" for="vg-mot" :error="$errors->first('motoristaId')">
                                <div class="flex gap-2">
                                    <x-ui.select id="vg-mot" wire:model="motoristaId" class="flex-1">
                                        <option value="">Escolha</option>
                                        @foreach ($this->motoristas as $m)
                                            <option value="{{ $m->id }}">{{ $m->nome }} · {{ $m->cpfFormatado() }}</option>
                                        @endforeach
                                    </x-ui.select>
                                    <x-ui.button variant="secondary" size="sm" wire:click="$toggle('novoMotoristaAberto')">Novo</x-ui.button>
                                </div>
                            </x-ui.field>
                            <x-ui.field label="Data do carregamento" for="vg-data" required :error="$errors->first('dataCarregamento')">
                                <x-ui.input id="vg-data" type="date" wire:model="dataCarregamento" />
                            </x-ui.field>
                        </div>

                        @if ($novoMotoristaAberto)
                            <div class="grid gap-3 rounded-lg border border-graphite-200 bg-graphite-50 p-4 sm:grid-cols-[1fr_12rem_auto] sm:items-end">
                                <x-ui.field label="Nome do motorista" for="nm-nome" :error="$errors->first('novoMotoristaNome')">
                                    <x-ui.input id="nm-nome" wire:model="novoMotoristaNome" maxlength="60" />
                                </x-ui.field>
                                <x-ui.field label="CPF" for="nm-cpf" :error="$errors->first('novoMotoristaCpf')">
                                    <x-ui.input id="nm-cpf" wire:model="novoMotoristaCpf" inputmode="numeric" maxlength="14" />
                                </x-ui.field>
                                <x-ui.button wire:click="salvarNovoMotorista">Cadastrar</x-ui.button>
                            </div>
                        @endif

                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ui.field label="Cavalo (tração)" for="vg-cav" :error="$errors->first('veiculoId')">
                                <x-ui.select id="vg-cav" wire:model="veiculoId">
                                    <option value="">Escolha</option>
                                    @foreach ($this->cavalos as $v)
                                        <option value="{{ $v->id }}">{{ $v->placaFormatada() }}{{ $v->deTerceiro() ? ' · terceiro' : '' }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                            <x-ui.field label="Carreta" for="vg-car1" :error="$errors->first('reboqueId')">
                                <x-ui.select id="vg-car1" wire:model="reboqueId">
                                    <option value="">Sem carreta</option>
                                    @foreach ($this->carretas as $v)
                                        <option value="{{ $v->id }}">{{ $v->placaFormatada() }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                            <x-ui.field label="Segunda carreta" for="vg-car2" :error="$errors->first('reboque2Id')">
                                <x-ui.select id="vg-car2" wire:model="reboque2Id">
                                    <option value="">Sem segunda carreta</option>
                                    @foreach ($this->carretas as $v)
                                        <option value="{{ $v->id }}">{{ $v->placaFormatada() }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                        </div>
                        <div>
                            <x-ui.button variant="ghost" size="sm" wire:click="$toggle('novoVeiculoAberto')">{{ $novoVeiculoAberto ? 'Fechar cadastro de veículo' : 'Cadastrar veículo' }}</x-ui.button>
                        </div>

                        @if ($novoVeiculoAberto)
                            <div class="grid gap-3 rounded-lg border border-graphite-200 bg-graphite-50 p-4 sm:grid-cols-3">
                                <x-ui.field label="Placa" for="nv-placa" :error="$errors->first('novoVeiculoPlaca')">
                                    <x-ui.input id="nv-placa" wire:model="novoVeiculoPlaca" maxlength="8" placeholder="ABC1D23" />
                                </x-ui.field>
                                <x-ui.field label="Tipo" for="nv-tipo">
                                    <x-ui.select id="nv-tipo" wire:model.live="novoVeiculoTipo">
                                        <option value="tracao">Cavalo (tração)</option>
                                        <option value="reboque">Carreta (reboque)</option>
                                    </x-ui.select>
                                </x-ui.field>
                                <x-ui.field label="UF do licenciamento" for="nv-uf" :error="$errors->first('novoVeiculoUf')">
                                    <x-ui.input id="nv-uf" wire:model="novoVeiculoUf" maxlength="2" />
                                </x-ui.field>
                                <x-ui.field label="Tara (kg)" for="nv-tara" :error="$errors->first('novoVeiculoTara')">
                                    <x-ui.input id="nv-tara" wire:model="novoVeiculoTara" inputmode="numeric" />
                                </x-ui.field>
                                @if ($novoVeiculoTipo === 'tracao')
                                    <x-ui.field label="Rodado" for="nv-rod">
                                        <x-ui.select id="nv-rod" wire:model="novoVeiculoRodado">
                                            @foreach (App\Models\Veiculo::TIPOS_RODADO as $codigo => $nome)
                                                <option value="{{ $codigo }}">{{ $nome }}</option>
                                            @endforeach
                                        </x-ui.select>
                                    </x-ui.field>
                                @endif
                                <x-ui.field label="Carroceria" for="nv-car" :error="$errors->first('novoVeiculoCarroceria')">
                                    <x-ui.select id="nv-car" wire:model="novoVeiculoCarroceria">
                                        @foreach (App\Models\Veiculo::TIPOS_CARROCERIA as $codigo => $nome)
                                            <option value="{{ $codigo }}">{{ $nome }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </x-ui.field>
                                <div class="sm:col-span-3 flex items-center justify-between gap-3">
                                    <p class="text-xs text-graphite-500">Veículo de terceiro? Complete o proprietário em <a href="{{ route('veiculos') }}" class="underline" wire:navigate>Veículos</a>.</p>
                                    <x-ui.button wire:click="salvarNovoVeiculo">Cadastrar veículo</x-ui.button>
                                </div>
                            </div>
                        @endif

                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ui.field label="Como o frete é cobrado" for="vg-modo" :error="$errors->first('freteModo')">
                                <x-ui.select id="vg-modo" wire:model.live="freteModo" :disabled="$travada">
                                    <option value="tonelada">Por tonelada</option>
                                    <option value="fechado">Valor fechado da viagem</option>
                                </x-ui.select>
                            </x-ui.field>
                            <x-ui.field :label="$freteModo === 'tonelada' ? 'Frete por tonelada (R$)' : 'Frete da viagem (R$)'" for="vg-frete"
                                :hint="$travada ? 'Já está no CT-e autorizado.' : null">
                                <x-ui.input id="vg-frete" wire:model="freteValor" placeholder="0,00" inputmode="decimal" :disabled="$travada" />
                            </x-ui.field>
                            <x-ui.field label="Pedágio (R$)" for="vg-ped" hint="Rateado entre os CT-e.">
                                <x-ui.input id="vg-ped" wire:model="pedagio" placeholder="0,00" inputmode="decimal" :disabled="$travada" />
                            </x-ui.field>
                        </div>

                        <x-ui.field label="Observações do CT-e" for="vg-obs" hint="Saem no campo de observações de todos os CT-e da viagem." :error="$errors->first('observacoes')">
                            <x-ui.textarea id="vg-obs" wire:model="observacoes" rows="2" maxlength="1000" :disabled="$travada" />
                        </x-ui.field>
                    </fieldset>

                    @can('transporte.operar')
                        @unless ($mdfeEmitido)
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm text-graphite-600">Frete total: <span class="num font-semibold text-graphite-900">R$ {{ Dinheiro::formatar($this->freteTotal) }}</span></p>
                                <x-ui.button type="submit" variant="secondary">Salvar e recalcular</x-ui.button>
                            </div>
                        @endunless
                    @endcan
                </form>
            </x-ui.card>

            {{-- 3. Documentos --}}
            <x-ui.card title="3. CT-e e MDF-e" subtitle="Um CT-e por grupo de NF-e com o mesmo remetente e destinatário, e um MDF-e para a viagem." :padded="false">
                @if ($viagem->ctes->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state title="Os CT-e aparecem aqui" description="Assim que a primeira NF-e entrar." />
                    </div>
                @else
                    <ul class="divide-y divide-graphite-100">
                        @foreach ($viagem->ctes as $cte)
                            <li class="grid gap-3 p-5" wire:key="cte-{{ $cte->id }}">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-graphite-900">CT-e {{ $cte->numeroFormatado() }}</p>
                                        <p class="text-xs text-graphite-500">
                                            {{ $cte->municipio_inicio }}/{{ $cte->uf_inicio }} → {{ $cte->municipio_fim }}/{{ $cte->uf_fim }}
                                            · {{ $cte->notas->count() }} NF-e · {{ number_format((float) $cte->peso_kg, 0, ',', '.') }} kg · CFOP {{ $cte->cfop }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="num text-sm font-semibold text-graphite-900">R$ {{ Dinheiro::formatar($cte->valor_total_centavos) }}</span>
                                        <x-ui.badge-status :status="$cte->status" />
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-graphite-600">
                                    <span>Paga o frete:</span>
                                    @if ($cte->status->transmissivel())
                                        @foreach (App\Models\Cte::TOMADORES as $tipo => $rotulo)
                                            <label class="flex items-center gap-1.5">
                                                <input type="radio" name="tomador-{{ $cte->id }}" value="{{ $tipo }}" @checked($cte->tomador_tipo === (string) $tipo)
                                                    wire:click="definirTomador({{ $cte->id }}, '{{ $tipo }}')" @cannot('transporte.operar') disabled @endcannot>
                                                {{ $rotulo }} ({{ $tipo === '3' ? $cte->destinatario['nome'] : $cte->remetente['nome'] }})
                                            </label>
                                        @endforeach
                                    @else
                                        <span class="font-semibold text-graphite-800">{{ $cte->tomador()['nome'] }}</span>
                                    @endif
                                    @if ($cte->icms_cst)
                                        <span>ICMS {{ $cte->icms_cst === 'SN' ? 'Simples Nacional' : 'CST '.$cte->icms_cst.' · R$ '.Dinheiro::formatar($cte->icms_valor_centavos) }}</span>
                                    @endif
                                    @if ($cte->fatura)
                                        <a href="{{ route('faturas.detalhe', $cte->fatura) }}" class="underline" wire:navigate>Fatura #{{ $cte->fatura_id }}</a>
                                    @endif
                                </div>

                                @if (in_array($cte->status, [CteStatus::Rejeitado, CteStatus::Denegado, CteStatus::EmProcessamento], true) && $cte->x_motivo)
                                    <x-ui.alert :variant="$cte->status === CteStatus::EmProcessamento ? 'warning' : 'danger'"><span class="whitespace-pre-line">{{ $cte->x_motivo }}</span></x-ui.alert>
                                @endif

                                @if ($cte->eventos->isNotEmpty())
                                    <ul class="grid gap-1 text-xs text-graphite-600">
                                        @foreach ($cte->eventos as $evento)
                                            <li>{{ $evento->tipo === 'cancelamento' ? 'Cancelado' : 'Carta de correção '.$evento->sequencia }}: {{ $evento->descricao }}</li>
                                        @endforeach
                                    </ul>
                                @endif

                                <div class="flex flex-wrap gap-2">
                                    @if (in_array($cte->status, [CteStatus::Autorizado, CteStatus::Cancelado], true))
                                        <x-ui.button variant="secondary" size="sm" :href="route('transporte.dacte', $cte)" target="_blank">DACTE</x-ui.button>
                                        <x-ui.button variant="ghost" size="sm" :href="route('transporte.cte.xml', $cte)">XML</x-ui.button>
                                    @endif
                                    @if ($cte->status === CteStatus::EmProcessamento)
                                        @can('transporte.operar')
                                            <x-ui.button variant="secondary" size="sm" wire:click="consultarCte({{ $cte->id }})">Consultar na SEFAZ</x-ui.button>
                                        @endcan
                                    @endif
                                    @if ($cte->status === CteStatus::Autorizado)
                                        @can('transporte.operar')
                                            <x-ui.button variant="ghost" size="sm" wire:click="abrirCorrecao({{ $cte->id }})">Carta de correção</x-ui.button>
                                        @endcan
                                        @can('transporte.cancelar')
                                            <x-ui.button variant="ghost" size="sm" wire:click="abrirCancelamento({{ $cte->id }})">Cancelar</x-ui.button>
                                        @endcan
                                    @endif
                                </div>

                                @if ($cteCancelarId === $cte->id)
                                    <form wire:submit="cancelarCte" class="grid gap-3 rounded-lg border border-danger-200 bg-danger-50 p-4">
                                        <x-ui.field label="Por que cancelar? (mínimo 15 caracteres)" for="cc-just" :error="$errors->first('justificativa')">
                                            <x-ui.textarea id="cc-just" wire:model="justificativa" rows="2" maxlength="255" />
                                        </x-ui.field>
                                        <div class="flex gap-2">
                                            <x-ui.button type="submit" variant="destructive">Cancelar o CT-e na SEFAZ</x-ui.button>
                                            <x-ui.button variant="ghost" wire:click="$set('cteCancelarId', null)">Voltar</x-ui.button>
                                        </div>
                                    </form>
                                @endif

                                @if ($cteCorrigirId === $cte->id)
                                    <form wire:submit="corrigirCte" class="grid gap-3 rounded-lg border border-graphite-200 bg-graphite-50 p-4 sm:grid-cols-[14rem_1fr]">
                                        <x-ui.field label="O que corrigir" for="cce-campo">
                                            <x-ui.select id="cce-campo" wire:model="campoCorrecao">
                                                @foreach (App\Services\Transporte\EventosCte::CORRECOES as $campo => $info)
                                                    <option value="{{ $campo }}">{{ $info['rotulo'] }}</option>
                                                @endforeach
                                            </x-ui.select>
                                        </x-ui.field>
                                        <x-ui.field label="Valor correto" for="cce-valor" :error="$errors->first('valorCorrecao')">
                                            <x-ui.input id="cce-valor" wire:model="valorCorrecao" maxlength="500" />
                                        </x-ui.field>
                                        <p class="text-xs text-graphite-500 sm:col-span-2">A lei não deixa corrigir valor, imposto, participantes nem datas por carta de correção.</p>
                                        <div class="flex gap-2 sm:col-span-2">
                                            <x-ui.button type="submit">Enviar carta de correção</x-ui.button>
                                            <x-ui.button variant="ghost" wire:click="$set('cteCorrigirId', null)">Voltar</x-ui.button>
                                        </div>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- MDF-e --}}
                <div class="grid gap-4 border-t border-graphite-200 bg-graphite-50/60 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-graphite-900">MDF-e {{ $mdfe?->numeroFormatado() ?? '' }}</p>
                            <p class="text-xs text-graphite-500">
                                @if ($mdfe)
                                    {{ $mdfe->municipio_carregamento }}/{{ $mdfe->uf_inicio }} → {{ $mdfe->uf_fim }}
                                    @if ($mdfe->percurso_ufs) · passando por {{ implode(', ', $mdfe->percurso_ufs) }} @endif
                                @else
                                    Sai depois que todos os CT-e forem autorizados.
                                @endif
                            </p>
                        </div>
                        @if ($mdfe)
                            <x-ui.badge-status :status="$mdfe->status" />
                        @endif
                    </div>

                    @if (! $mdfeEmitido && $todosAutorizados && $this->pendenciasMdfe)
                        <x-ui.alert variant="warning" title="Para o MDF-e sair falta">
                            <ul class="list-disc pl-4">
                                @foreach ($this->pendenciasMdfe as $pendencia)
                                    <li>{{ $pendencia }}</li>
                                @endforeach
                            </ul>
                        </x-ui.alert>
                    @endif

                    @if ($mdfe && in_array($mdfe->status, [MdfeStatus::Rejeitado, MdfeStatus::EmProcessamento], true) && $mdfe->x_motivo)
                        <x-ui.alert :variant="$mdfe->status === MdfeStatus::EmProcessamento ? 'warning' : 'danger'"><span class="whitespace-pre-line">{{ $mdfe->x_motivo }}</span></x-ui.alert>
                    @endif

                    @if (! $mdfeEmitido && $mdfe?->status !== MdfeStatus::Cancelado)
                        @can('transporte.operar')
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-ui.field label="UFs de passagem" for="md-perc" hint="Só as do meio, na ordem da viagem. Ex.: MG, GO.">
                                    <x-ui.input id="md-perc" wire:model="percurso" wire:change="salvarMdfeRascunho" placeholder="Nenhuma" />
                                </x-ui.field>
                                <x-ui.field label="Número da averbação do seguro" for="md-aver" hint="A seguradora devolve ao averbar a carga.">
                                    <x-ui.input id="md-aver" wire:model="averbacoes" wire:change="salvarMdfeRascunho" />
                                </x-ui.field>
                            </div>
                        @endcan
                    @endif

                    <div class="flex flex-wrap gap-2">
                        @if ($mdfe && in_array($mdfe->status, [MdfeStatus::Autorizado, MdfeStatus::Encerrado, MdfeStatus::Cancelado], true))
                            <x-ui.button variant="secondary" size="sm" :href="route('transporte.damdfe', $mdfe)" target="_blank">DAMDFE</x-ui.button>
                            <x-ui.button variant="ghost" size="sm" :href="route('transporte.mdfe.xml', $mdfe)">XML</x-ui.button>
                        @endif
                        @if ($mdfe?->status === MdfeStatus::EmProcessamento)
                            @can('transporte.operar')
                                <x-ui.button variant="secondary" size="sm" wire:click="consultarMdfe">Consultar na SEFAZ</x-ui.button>
                            @endcan
                        @endif
                        @if ($mdfe?->status === MdfeStatus::Autorizado)
                            @can('transporte.operar')
                                <x-ui.button size="sm" wire:click="$toggle('encerrarAberto')">Encerrar viagem</x-ui.button>
                            @endcan
                            @can('transporte.cancelar')
                                <x-ui.button variant="ghost" size="sm" wire:click="$toggle('cancelarMdfeAberto')">Cancelar MDF-e</x-ui.button>
                            @endcan
                        @endif
                    </div>

                    @if ($encerrarAberto && $mdfe?->status === MdfeStatus::Autorizado)
                        <form wire:submit="encerrarMdfe" class="grid gap-3 rounded-lg border border-success-300 bg-success-50 p-4 sm:grid-cols-[1fr_12rem_auto] sm:items-end">
                            <x-ui.field label="Onde a viagem terminou" for="enc-mun" :error="$errors->first('encerramento')">
                                <x-ui.select id="enc-mun" wire:model="municipioEncerramento">
                                    @foreach ($viagem->ctes->unique('municipio_fim_codigo') as $cteDestino)
                                        <option value="{{ $cteDestino->municipio_fim_codigo }}">{{ $cteDestino->municipio_fim }}/{{ $cteDestino->uf_fim }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                            <x-ui.field label="Data" for="enc-data">
                                <x-ui.input id="enc-data" type="date" wire:model="dataEncerramento" />
                            </x-ui.field>
                            <x-ui.button type="submit">Encerrar na SEFAZ</x-ui.button>
                        </form>
                    @endif

                    @if ($cancelarMdfeAberto && $mdfe?->status === MdfeStatus::Autorizado)
                        <form wire:submit="cancelarMdfe" class="grid gap-3 rounded-lg border border-danger-200 bg-danger-50 p-4">
                            <x-ui.field label="Por que cancelar? (até 24 h depois da autorização)" for="cm-just" :error="$errors->first('justificativa')">
                                <x-ui.textarea id="cm-just" wire:model="justificativa" rows="2" maxlength="255" />
                            </x-ui.field>
                            <div class="flex gap-2">
                                <x-ui.button type="submit" variant="destructive">Cancelar o MDF-e na SEFAZ</x-ui.button>
                                <x-ui.button variant="ghost" wire:click="$set('cancelarMdfeAberto', false)">Voltar</x-ui.button>
                            </div>
                        </form>
                    @endif
                </div>
            </x-ui.card>

            {{-- Faturamento --}}
            @if ($viagem->ctes->contains(fn ($c) => $c->status === CteStatus::Autorizado))
                <x-ui.card title="Faturar o frete" subtitle="Uma fatura por quem paga o frete, com cobrança Pix. O cliente é cadastrado sozinho a partir do CT-e.">
                    @if ($aFaturar->isEmpty())
                        <p class="text-sm text-graphite-600">Todos os CT-e autorizados já estão faturados.</p>
                    @else
                        @can('transporte.operar')
                            <form wire:submit="faturar" class="flex flex-wrap items-end gap-3">
                                <x-ui.field label="Vencimento" for="ft-venc" :hint="'Em branco: '.$viagem->emitente->configuracaoTransporte()->prazo_fatura_dias.' dias.'">
                                    <x-ui.input id="ft-venc" type="date" wire:model="vencimentoFatura" />
                                </x-ui.field>
                                <x-ui.button type="submit">Faturar {{ $aFaturar->count() }} CT-e · R$ {{ Dinheiro::formatar((int) $aFaturar->sum('valor_total_centavos')) }}</x-ui.button>
                            </form>
                        @endcan
                    @endif
                </x-ui.card>
            @endif
        </div>

        {{-- Linha do tempo --}}
        <aside class="min-w-0">
            <x-ui.card title="Linha do tempo">
                <ol class="grid gap-3">
                    @forelse ($viagem->eventos->take(30) as $evento)
                        <li class="border-l-2 border-graphite-200 pl-3">
                            <p class="text-sm text-graphite-800">{{ $evento->descricao }}</p>
                            <p class="text-xs text-graphite-500">{{ $evento->created_at?->format('d/m H:i') }}{{ $evento->user ? ' · '.$evento->user->name : '' }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-graphite-500">Nada ainda.</li>
                    @endforelse
                </ol>
            </x-ui.card>
        </aside>
    </div>
</div>
