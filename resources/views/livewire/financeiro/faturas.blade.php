<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Financeiro"
        title="Faturas"
        description="Uma fatura por acordo com o cliente. As parcelas dela viram títulos em Contas a receber, e o link vai para o cliente pagar por Pix.">
        <x-slot:actions>
            @can('financeiro.gerenciar')
                <x-ui.button wire:click="$toggle('formularioAberto')">
                    {{ $formularioAberto ? 'Cancelar' : 'Nova fatura' }}
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    @can('financeiro.gerenciar')
        @if (blank($this->emitente->chave_pix))
            <x-ui.alert variant="info" title="Cobrança Pix desligada">
                Sem chave cadastrada a parcela nasce sem código de pagamento.
                @can('emitente.gerenciar')
                    Cadastre a chave em <a href="{{ route('emitente') }}" class="underline">Emitente</a>.
                @else
                    Peça a quem administra o emitente para cadastrar a chave.
                @endcan
            </x-ui.alert>
        @endif

        @if ($formularioAberto)
            <x-ui.card title="Nova fatura"
                subtitle="Gere as parcelas a partir do total, e ajuste linha a linha se a negociação foi outra.">
                <form wire:submit="lancar" class="grid gap-5">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.field label="Título" for="ft-titulo" required :error="$errors->first('titulo')">
                            <x-ui.input id="ft-titulo" wire:model="titulo" maxlength="160" placeholder="Consultoria de setembro" />
                        </x-ui.field>

                        <x-ui.field label="Valor total" for="ft-valor" required :error="$errors->first('valor')">
                            <x-ui.input id="ft-valor" wire:model="valor" placeholder="R$ 0,00" />
                        </x-ui.field>

                        <x-ui.field label="Parcelas" for="ft-parc" required :error="$errors->first('parcelas')">
                            <x-ui.input id="ft-parc" type="number" min="1" max="120" wire:model="parcelas" />
                        </x-ui.field>

                        <x-ui.field label="Primeiro vencimento" for="ft-venc" required
                            :error="$errors->first('primeiroVencimento')" hint="As demais caem de mês em mês.">
                            <x-ui.input id="ft-venc" type="date" wire:model="primeiroVencimento" />
                        </x-ui.field>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Cliente" for="ft-cli" :error="$errors->first('pessoaId')">
                            <x-ui.select id="ft-cli" wire:model="pessoaId">
                                <option value="">Sem cliente vinculado</option>
                                @foreach ($this->clientes as $cliente)
                                    <option value="{{ $cliente->id }}">{{ $cliente->razao_social }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>

                        @if ($this->centrosReceita->isNotEmpty())
                            <x-ui.field label="Centro de receita" for="ft-centro" required :error="$errors->first('centroCustoId')">
                                <x-ui.select id="ft-centro" wire:model="centroCustoId">
                                    <option value="">Escolha</option>
                                    @foreach ($this->centrosReceita as $centro)
                                        <option value="{{ $centro->id }}">{{ $centro->codigo }} · {{ $centro->nome }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                        @endif
                    </div>

                    @if ($this->cobraEmitirAgora)
                        <label class="flex items-start gap-2.5 rounded-md border border-graphite-200 bg-graphite-50 px-3 py-2.5">
                            <input type="checkbox" wire:model="mensalidade" class="mt-0.5 size-4 rounded border-graphite-300 text-graphite-900 focus:ring-primary-600/25">
                            <span>
                                <span class="font-medium text-graphite-900">Mensalidade do EmitirAgora</span>
                                <span class="block text-graphite-600">Cada parcela paga libera mais um mês na conta do cliente, achada pelo CNPJ dele.</span>
                            </span>
                        </label>
                    @endif

                    <x-ui.field label="Observações para o cliente" for="ft-obs" :error="$errors->first('observacoes')" hint="Aparecem na página pública da fatura.">
                        <x-ui.textarea id="ft-obs" wire:model="observacoes" rows="2" maxlength="1000" />
                    </x-ui.field>

                    <div class="flex flex-wrap gap-2">
                        <x-ui.button type="button" variant="secondary" wire:click="gerarLinhas">Gerar parcelas</x-ui.button>
                        <x-ui.button type="submit">Lançar</x-ui.button>
                    </div>

                    @if ($linhas !== [])
                        <div class="border-t border-graphite-200 pt-5">
                            <p class="etiqueta mb-3 text-graphite-500">
                                Parcelas · total {{ App\Support\Dinheiro::formatar(collect($linhas)->sum(fn ($l) => App\Support\Dinheiro::emCentavos((string) ($l['valor'] ?? '')))) }}
                            </p>

                            <div class="grid gap-3">
                                @foreach ($linhas as $i => $linha)
                                    <div class="grid min-w-0 gap-3 sm:grid-cols-[1fr_9rem_10rem_auto] sm:items-start">
                                        <x-ui.field :label="$i === 0 ? 'Descrição' : null" :error="$errors->first('linhas.'.$i.'.descricao')">
                                            <x-ui.input wire:model="linhas.{{ $i }}.descricao" maxlength="160" />
                                        </x-ui.field>

                                        <x-ui.field :label="$i === 0 ? 'Valor' : null" :error="$errors->first('linhas.'.$i.'.valor')">
                                            <x-ui.input wire:model="linhas.{{ $i }}.valor" placeholder="R$ 0,00" />
                                        </x-ui.field>

                                        <x-ui.field :label="$i === 0 ? 'Vencimento' : null" :error="$errors->first('linhas.'.$i.'.vencimento')">
                                            <x-ui.input type="date" wire:model="linhas.{{ $i }}.vencimento" />
                                        </x-ui.field>

                                        <div class="{{ $i === 0 ? 'sm:mt-6' : '' }}">
                                            <x-ui.button type="button" variant="ghost" size="sm"
                                                wire:click="removerLinha({{ $i }})"
                                                aria-label="Remover parcela {{ $i + 1 }}">Remover</x-ui.button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-3">
                                <x-ui.button type="button" variant="ghost" size="sm" wire:click="adicionarLinha">Acrescentar parcela</x-ui.button>
                            </div>
                        </div>
                    @endif

                    @error('linhas')
                        <p class="text-xs text-danger-700">{{ $message }}</p>
                    @enderror
                </form>
            </x-ui.card>
        @endif
    @endcan

    <x-ui.card title="Todas as faturas" subtitle="Mais recente primeiro" :padded="false">
        @if ($this->faturas->isEmpty())
            <div class="p-5">
                <x-ui.empty-state title="Nenhuma fatura emitida" description="Crie uma fatura com uma ou mais parcelas e envie o link ao cliente." />
            </div>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200 text-left">
                        <th class="etiqueta px-5 py-2 text-graphite-500">Fatura</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Cliente</th>
                        <th class="etiqueta px-5 py-2 text-right text-graphite-500">Total</th>
                        <th class="etiqueta px-5 py-2 text-right text-graphite-500">Em aberto</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Próximo vencimento</th>
                        <th class="etiqueta px-5 py-2 text-right text-graphite-500"><span class="sr-only">Abrir</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->faturas as $fatura)
                        @php
                            $ativas = $fatura->parcelas->where('status', '!=', 'cancelado');
                            $proxima = $fatura->parcelas->where('status', 'pendente')->sortBy('vencimento')->first();
                        @endphp
                        <tr class="border-b border-graphite-100 last:border-0 {{ $fatura->status === 'cancelada' ? 'opacity-60' : '' }}" wire:key="fatura-{{ $fatura->id }}">
                            <td class="px-5 py-3">
                                <a href="{{ route('faturas.detalhe', $fatura) }}" wire:navigate class="font-medium text-graphite-900 hover:underline">{{ $fatura->titulo }}</a>
                                <p class="text-xs text-graphite-500">
                                    @if ($fatura->centroCusto){{ $fatura->centroCusto->codigo }} · {{ $fatura->centroCusto->nome }}@else Sem centro de receita @endif
                                    @if ($fatura->status === 'cancelada') · <span class="etiqueta text-danger-700">cancelada</span>@endif
                                </p>
                            </td>
                            <td class="px-5 py-3 text-graphite-700">{{ $fatura->destinatario?->razao_social ?: '—' }}</td>
                            <td class="num px-5 py-3 text-right font-semibold text-graphite-900">{{ App\Support\Dinheiro::formatar((int) $ativas->sum('valor_centavos')) }}</td>
                            <td class="num px-5 py-3 text-right text-graphite-700">{{ App\Support\Dinheiro::formatar((int) $ativas->where('status', 'pendente')->sum('valor_centavos')) }}</td>
                            <td class="num px-5 py-3 text-graphite-700">{{ $proxima ? $proxima->vencimento->format('d/m/Y') : 'Tudo pago' }}</td>
                            <td class="px-5 py-3 text-right">
                                <x-ui.button :href="route('faturas.detalhe', $fatura)" wire:navigate variant="ghost" size="sm">Abrir</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
