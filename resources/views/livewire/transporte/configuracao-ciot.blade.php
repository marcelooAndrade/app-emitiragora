@php
    $podeEditar = auth()->user()->can('transporte.configurar');
    $emProducao = $this->emitente->ambiente === App\Enums\Fiscal\Ambiente::Producao;
@endphp

<div class="grid gap-6">
    <x-ui.page-header eyebrow="Configuração" title="CIOT"
        description="Toda viagem precisa de CIOT antes do MDF-e, com caminhão próprio ou de terceiro (Resolução ANTT 6.078/2026). Escolha quem gera o CIOT desta empresa." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif
    @error('teste')
        <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
    @enderror

    <form wire:submit="salvar" class="grid gap-6">
        <fieldset @disabled(! $podeEditar) class="grid gap-6">
            <x-ui.card title="Quem gera o CIOT" subtitle="Trocar de empresa não mexe nos CIOT já gerados: cada um continua com a empresa que o gerou.">
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($this->provedores as $chave => $opcao)
                        <label wire:key="provedor-{{ $chave }}" @class([
                            'flex cursor-pointer gap-3 rounded-lg border p-4 transition-colors',
                            'border-graphite-900 bg-graphite-50' => $provedor === $chave,
                            'border-graphite-200 hover:border-graphite-300' => $provedor !== $chave,
                        ])>
                            <input type="radio" wire:model.live="provedor" value="{{ $chave }}" class="mt-1">
                            <span class="grid gap-1">
                                <span class="font-semibold text-graphite-900">{{ $opcao['nome'] }}</span>
                                <span class="text-sm text-graphite-600">{{ $opcao['descricao'] }}</span>
                                <span @class(['text-xs font-semibold', 'text-success-700' => $opcao['producao'], 'text-ember-800' => ! $opcao['producao']])>
                                    {{ $opcao['producao'] ? 'Liberado em produção' : 'Só homologação, até o teste nesta empresa' }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @if ($emProducao && ! ($this->provedores[$provedor]['producao'] ?? false))
                    <x-ui.alert variant="warning" class="mt-4">
                        Esta empresa está em produção e {{ $this->provedores[$provedor]['nome'] ?? $provedor }} ainda não está liberado para produção. Até lá, o CIOT das viagens é digitado.
                    </x-ui.alert>
                @endif
            </x-ui.card>

            @if ($this->campos !== [])
                <div class="grid gap-6 lg:grid-cols-2">
                    @foreach (['homologacao' => 'Homologação', 'producao' => 'Produção'] as $ambiente => $rotulo)
                        <x-ui.card :title="'Credenciais de '.mb_strtolower($rotulo)" :subtitle="$ambiente === ($emProducao ? 'producao' : 'homologacao') ? 'Ambiente atual desta empresa.' : null">
                            <div class="grid gap-4">
                                @foreach ($this->campos as $campo => $definicao)
                                    @php
                                        $id = "ciot-{$ambiente}-{$campo}";
                                        $tipo = $definicao['tipo'] ?? 'texto';
                                        $segredo = $definicao['segredo'] ?? false;
                                        $salvo = $this->salvos[$ambiente][$campo] ?? false;
                                    @endphp
                                    @if ($tipo === 'checkbox')
                                        <label class="flex items-start gap-2 text-sm text-graphite-700">
                                            <input type="checkbox" wire:model="{{ $ambiente }}.{{ $campo }}" class="mt-0.5">
                                            <span>{{ $definicao['rotulo'] }}</span>
                                        </label>
                                    @elseif ($tipo === 'select')
                                        <x-ui.field :label="$definicao['rotulo']" :for="$id" :hint="$definicao['ajuda'] ?? null">
                                            <x-ui.select :id="$id" wire:model="{{ $ambiente }}.{{ $campo }}">
                                                @foreach ($definicao['opcoes'] ?? [] as $valor => $nome)
                                                    <option value="{{ $valor }}">{{ $nome }}</option>
                                                @endforeach
                                            </x-ui.select>
                                        </x-ui.field>
                                    @else
                                        <x-ui.field :label="$definicao['rotulo']" :for="$id"
                                            :hint="$segredo && $salvo ? 'Salvo. Em branco, continua o mesmo.' : ($definicao['ajuda'] ?? null)"
                                            :error="$errors->first($ambiente.'.'.$campo)">
                                            <x-ui.input :id="$id" :type="$segredo ? 'password' : 'text'" wire:model="{{ $ambiente }}.{{ $campo }}" maxlength="200" autocomplete="off" />
                                        </x-ui.field>
                                    @endif
                                @endforeach
                            </div>
                        </x-ui.card>
                    @endforeach
                </div>
            @endif

            <x-ui.card title="Como seus clientes pagam o frete" subtitle="A ANTT pede a forma de pagamento também na viagem com caminhão próprio. O prazo é o da fatura, em CT-e e MDF-e.">
                <div class="grid gap-4 sm:grid-cols-4">
                    <x-ui.field label="Forma" for="rec-tipo" required>
                        <x-ui.select id="rec-tipo" wire:model.live="recebimentoTipo">
                            <option value="pix">Pix</option>
                            <option value="transferencia">Transferência bancária</option>
                            <option value="boleto">Boleto</option>
                        </x-ui.select>
                    </x-ui.field>
                    @if ($recebimentoTipo === 'pix')
                        <div class="text-sm text-graphite-600 sm:col-span-3 sm:self-end">
                            @if (filled($this->emitente->chave_pix))
                                Recebe na chave <span class="font-semibold text-graphite-900">{{ $this->emitente->chave_pix }}</span>, da tela Empresa.
                            @else
                                A empresa ainda não tem chave Pix. Informe em <a href="{{ route('emitente') }}" wire:navigate class="font-semibold underline-offset-2 hover:underline">Configurações, Empresa</a>.
                            @endif
                        </div>
                    @elseif ($recebimentoTipo === 'transferencia')
                        <x-ui.field label="Banco" for="rec-banco" hint="3 dígitos" :error="$errors->first('recebimentoBanco')">
                            <x-ui.input id="rec-banco" wire:model="recebimentoBanco" inputmode="numeric" maxlength="3" placeholder="001" />
                        </x-ui.field>
                        <x-ui.field label="Agência" for="rec-ag" :error="$errors->first('recebimentoAgencia')">
                            <x-ui.input id="rec-ag" wire:model="recebimentoAgencia" maxlength="10" />
                        </x-ui.field>
                        <x-ui.field label="Conta" for="rec-conta" :error="$errors->first('recebimentoConta')">
                            <x-ui.input id="rec-conta" wire:model="recebimentoConta" maxlength="20" />
                        </x-ui.field>
                    @endif
                </div>
            </x-ui.card>
        </fieldset>

        @if ($podeEditar)
            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit">Salvar</x-ui.button>
                @if ($this->campos !== [])
                    <x-ui.button type="button" variant="secondary" wire:click="testarConexao" wire:loading.attr="disabled" wire:target="testarConexao">
                        <span wire:loading.remove wire:target="testarConexao">Salvar e testar conexão</span>
                        <span wire:loading wire:target="testarConexao">Testando...</span>
                    </x-ui.button>
                @endif
            </div>
        @endif
    </form>
</div>
