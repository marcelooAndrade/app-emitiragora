@php $podeEditar = auth()->user()->can('transporte.configurar'); @endphp

<div class="grid gap-6">
    <x-ui.page-header eyebrow="Configuração" title="Transporte"
        description="O que o CT-e e o MDF-e precisam além do cadastro da empresa: RNTRC, séries, seguro e averbação da carga, e o ICMS do frete." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    <x-ui.card title="Empresa transportadora" :subtitle="'Próximo CT-e: '.$this->proximos['cte'].' · próximo MDF-e: '.$this->proximos['mdfe']">
        <form wire:submit="salvar" class="grid gap-5">
            <fieldset @disabled(! $podeEditar) class="grid gap-5">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.field label="RNTRC da empresa" hint="8 dígitos, do registro na ANTT." :error="$errors->first('rntrc')">
                        <x-ui.input wire:model="rntrc" inputmode="numeric" maxlength="8" />
                    </x-ui.field>
                    <x-ui.field label="Série do CT-e" required :error="$errors->first('cteSerie')">
                        <x-ui.input wire:model="cteSerie" inputmode="numeric" />
                    </x-ui.field>
                    <x-ui.field label="Série do MDF-e" required :error="$errors->first('mdfeSerie')">
                        <x-ui.input wire:model="mdfeSerie" inputmode="numeric" />
                    </x-ui.field>
                    <x-ui.field label="Prazo da fatura (dias)" required :error="$errors->first('prazoFaturaDias')">
                        <x-ui.input wire:model="prazoFaturaDias" inputmode="numeric" />
                    </x-ui.field>
                </div>
                <div class="grid gap-4 sm:grid-cols-[12rem_1fr_16rem]">
                    <x-ui.field label="CFOP" hint="Dentro do estado. Fora, vira 6xxx sozinho." required :error="$errors->first('cfop')">
                        <x-ui.input wire:model="cfop" inputmode="numeric" maxlength="4" />
                    </x-ui.field>
                    <x-ui.field label="Natureza da operação" required :error="$errors->first('naturezaOperacao')">
                        <x-ui.input wire:model="naturezaOperacao" maxlength="60" />
                    </x-ui.field>
                    <x-ui.field label="No MDF-e, a empresa é" required>
                        <x-ui.select wire:model="tipoEmitenteMdfe">
                            <option value="1">Transportadora (presta o frete)</option>
                            <option value="2">Dona da carga (carga própria)</option>
                        </x-ui.select>
                    </x-ui.field>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.field label="Seguradora da carga" :error="$errors->first('seguradoraNome')">
                        <x-ui.input wire:model="seguradoraNome" maxlength="30" />
                    </x-ui.field>
                    <x-ui.field label="CNPJ da seguradora" :error="$errors->first('seguradoraCnpj')">
                        <x-ui.input wire:model="seguradoraCnpj" inputmode="numeric" />
                    </x-ui.field>
                    <x-ui.field label="Apólice" :error="$errors->first('apolice')">
                        <x-ui.input wire:model="apolice" maxlength="20" />
                    </x-ui.field>
                    <x-ui.field label="Quem contratou o seguro">
                        <x-ui.select wire:model="responsavelSeguro">
                            <option value="1">A transportadora</option>
                            <option value="2">O contratante do frete</option>
                        </x-ui.select>
                    </x-ui.field>
                </div>

                <div class="grid gap-3 border-t border-graphite-100 pt-5">
                    <div>
                        <p class="font-semibold text-graphite-900">Averbação automática e terceiros</p>
                        <p class="text-xs text-graphite-500">
                            Com a AT&amp;M configurada, cada CT-e autorizado é averbado sozinho e o número entra no MDF-e.
                            @if ($this->config->temAtm())
                                <span class="font-semibold text-success-700">Integração ligada.</span>
                            @endif
                            @unless (config('fiscal.atm.producao'))
                                Por enquanto só em homologação, como no Transm.
                            @endunless
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.field label="Usuário da AT&M" for="atm-usuario" hint="Em branco desliga a integração." :error="$errors->first('atmUsuario')">
                            <x-ui.input id="atm-usuario" wire:model="atmUsuario" maxlength="100" autocomplete="off" />
                        </x-ui.field>
                        <x-ui.field label="Senha da AT&M" for="atm-senha" :hint="$this->config->atm_senha ? 'Salva. Em branco, continua a mesma.' : null" :error="$errors->first('atmSenha')">
                            <x-ui.input id="atm-senha" type="password" wire:model="atmSenha" maxlength="100" autocomplete="new-password" />
                        </x-ui.field>
                        <x-ui.field label="Código AT&M" for="atm-codigo" :error="$errors->first('atmCodigo')">
                            <x-ui.input id="atm-codigo" wire:model="atmCodigo" maxlength="30" autocomplete="off" />
                        </x-ui.field>
                        <x-ui.field label="Adiantamento ao terceiro (%)" for="adiant-pct" hint="Sugerido no contrato do frete." required :error="$errors->first('adiantamentoPercentual')">
                            <x-ui.input id="adiant-pct" wire:model="adiantamentoPercentual" inputmode="numeric" maxlength="3" />
                        </x-ui.field>
                    </div>
                </div>
            </fieldset>
            @if ($podeEditar)
                <div><x-ui.button type="submit">Salvar</x-ui.button></div>
            @endif
        </form>
    </x-ui.card>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <x-ui.card title="ICMS do frete" subtitle="A regra mais específica para a rota vence. Simples Nacional sem regra sai como ICMSSN." :padded="false">
            @if ($this->regras->isEmpty())
                <div class="p-5"><x-ui.empty-state title="Nenhuma regra" description="Sem regra, só emitente do Simples Nacional consegue emitir CT-e." /></div>
            @else
                <x-ui.table>
                    <thead>
                        <tr class="border-b border-graphite-200 text-left">
                            <th class="etiqueta px-5 py-2 text-graphite-500">Regra</th>
                            <th class="etiqueta px-5 py-2 text-graphite-500">Rota</th>
                            <th class="etiqueta px-5 py-2 text-graphite-500">CST</th>
                            <th class="etiqueta px-5 py-2 text-right text-graphite-500">Alíquota</th>
                            <th class="px-5 py-2"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->regras as $r)
                            <tr class="border-b border-graphite-100 last:border-0" wire:key="r-{{ $r->id }}">
                                <td class="px-5 py-3 font-semibold text-graphite-900">{{ $r->nome }}</td>
                                <td class="px-5 py-3 text-graphite-700">{{ $r->rotuloRota() }}@if ($r->percurso_ufs) <span class="text-xs text-graphite-500">via {{ implode(', ', $r->percurso_ufs) }}</span>@endif</td>
                                <td class="px-5 py-3 text-graphite-700">{{ $r->cst }}</td>
                                <td class="num px-5 py-3 text-right text-graphite-700">{{ number_format((float) $r->aliquota, 2, ',', '.') }}%</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    @if ($podeEditar)
                                        <x-ui.button variant="ghost" size="sm" wire:click="editarRegra({{ $r->id }})">Editar</x-ui.button>
                                        <x-ui.button variant="ghost" size="sm" wire:click="excluirRegra({{ $r->id }})" wire:confirm="Excluir a regra {{ $r->nome }}?">Excluir</x-ui.button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>

        @if ($podeEditar)
            <x-ui.card :title="$regraId ? 'Editar regra' : 'Nova regra de ICMS'">
                <form wire:submit="salvarRegra" class="grid gap-3">
                    <x-ui.field label="Nome" required :error="$errors->first('regraNome')">
                        <x-ui.input wire:model="regraNome" maxlength="80" placeholder="SP para MG" />
                    </x-ui.field>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.field label="UF de origem" hint="Em branco vale para todas." :error="$errors->first('regraUfOrigem')">
                            <x-ui.input wire:model="regraUfOrigem" maxlength="2" />
                        </x-ui.field>
                        <x-ui.field label="UF de destino" hint="Em branco vale para todas." :error="$errors->first('regraUfDestino')">
                            <x-ui.input wire:model="regraUfDestino" maxlength="2" />
                        </x-ui.field>
                    </div>
                    <x-ui.field label="Tributação (CST)" required :error="$errors->first('regraCst')">
                        <x-ui.select wire:model.live="regraCst">
                            @foreach (App\Models\RegraIcmsTransporte::CSTS as $codigo => $rotulo)
                                <option value="{{ $codigo }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    @if (in_array($regraCst, ['00', '20', '90'], true))
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.field label="Alíquota (%)" required :error="$errors->first('regraAliquota')">
                                <x-ui.input wire:model="regraAliquota" inputmode="decimal" placeholder="12,00" />
                            </x-ui.field>
                            <x-ui.field label="Redução da base (%)">
                                <x-ui.input wire:model="regraReducao" inputmode="decimal" placeholder="0,00" />
                            </x-ui.field>
                        </div>
                    @endif
                    <x-ui.field label="UFs de passagem do MDF-e" hint="Só as do meio, na ordem. Ex.: MG, GO.">
                        <x-ui.input wire:model="regraPercurso" />
                    </x-ui.field>
                    <div class="flex gap-2">
                        <x-ui.button type="submit">{{ $regraId ? 'Salvar regra' : 'Cadastrar regra' }}</x-ui.button>
                        @if ($regraId)
                            <x-ui.button variant="ghost" wire:click="novaRegra">Cancelar</x-ui.button>
                        @endif
                    </div>
                </form>
            </x-ui.card>
        @endif
    </div>
</div>
