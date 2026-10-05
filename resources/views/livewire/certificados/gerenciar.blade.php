<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Emitente"
        title="Certificado digital"
        :description="$this->emitente->razao_social" />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    @error('certificado')
        <x-ui.alert variant="danger" title="Não foi possível usar este certificado">{{ $message }}</x-ui.alert>
    @enderror

    {{-- Certificado ativo --}}
    <x-ui.card title="Certificado ativo">
        @if ($this->ativo)
            @php $dias = $this->ativo->diasParaVencer(); @endphp

            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                <div><dt class="etiqueta text-graphite-500">Titular</dt><dd class="mt-0.5">{{ $this->ativo->titular }}</dd></div>
                <div><dt class="etiqueta text-graphite-500">CNPJ</dt><dd class="num mt-0.5">{{ $this->ativo->cnpj }}</dd></div>
                <div><dt class="etiqueta text-graphite-500">Válido até</dt><dd class="num mt-0.5">{{ $this->ativo->valido_ate->format('d/m/Y') }}</dd></div>
                <div>
                    <dt class="etiqueta text-graphite-500">Situação</dt>
                    <dd class="mt-0.5">
                        @if ($this->ativo->vencido())
                            <span class="font-semibold text-danger-700">Vencido</span>
                        @elseif ($dias <= 30)
                            <span class="font-semibold text-ember-700 num">Vence em {{ $dias }} dias</span>
                        @else
                            <span class="font-semibold text-success-700 num">Válido por mais {{ $dias }} dias</span>
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($this->ativo->convertido_de_legado)
                <x-ui.alert variant="info" class="mt-4">
                    Este arquivo usava um algoritmo antigo, desabilitado no OpenSSL 3, e foi convertido
                    automaticamente no envio. Na próxima renovação, peça o A1 em formato atual.
                </x-ui.alert>
            @endif

            @if (! $this->ativo->vencido() && $dias <= 30)
                <x-ui.alert variant="warning" class="mt-4" title="Renovação necessária">
                    Sem certificado válido o sistema não assina nem transmite CT-e e MDF-e. Procure sua certificadora.
                </x-ui.alert>
            @endif
        @else
            <x-ui.empty-state
                title="Nenhum certificado cadastrado"
                description="Envie o arquivo A1 (.pfx ou .p12) para que o sistema possa assinar e transmitir CT-e e MDF-e." />
        @endif
    </x-ui.card>

    {{-- Envio --}}
    @can('certificado.gerenciar')
        <x-ui.card title="Enviar certificado A1" subtitle="O arquivo é cifrado antes de ir para a área privada. A senha nunca aparece em log nem em tela.">
            <form wire:submit="enviar" class="grid gap-4">
                <x-ui.field label="Arquivo .pfx ou .p12" for="cert-arquivo" required :error="$errors->first('arquivo')">
                    <x-ui.input id="cert-arquivo" type="file" wire:model="arquivo" accept=".pfx,.p12" />
                </x-ui.field>

                <x-ui.field label="Senha do certificado" for="cert-senha" required :error="$errors->first('senha')">
                    <x-ui.input id="cert-senha" type="password" wire:model="senha" autocomplete="off" />
                </x-ui.field>

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit" size="lg">
                        <span wire:loading.remove wire:target="enviar">Enviar certificado</span>
                        <span wire:loading wire:target="enviar">Validando...</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endcan

    {{-- Ambiente --}}
    @can('emitente.ativar-producao')
        <x-ui.card title="Ambiente">
            @error('ambiente')
                <x-ui.alert variant="danger" class="mb-4">{{ $message }}</x-ui.alert>
            @enderror

            @if ($this->emitente->ambiente === \App\Enums\Fiscal\Ambiente::Homologacao)
                <p class="text-sm text-graphite-600">
                    Este emitente está em <strong>homologação</strong>. Os documentos não têm valor fiscal.
                    A virada exige certificado válido e responsável técnico configurado.
                </p>

                <form wire:submit="ativarProducao" class="mt-4 grid gap-4 sm:max-w-sm">
                    <x-ui.field
                        label="Digite PRODUCAO para confirmar"
                        for="conf-prod"
                        hint="A partir daqui todo CT-e e MDF-e emitido tem valor fiscal."
                        :error="$errors->first('confirmacaoProducao')">
                        <x-ui.input id="conf-prod" wire:model="confirmacaoProducao" autocomplete="off" placeholder="PRODUCAO" />
                    </x-ui.field>
                    <div><x-ui.button type="submit" variant="destructive">Ativar produção</x-ui.button></div>
                </form>
            @else
                <x-ui.alert variant="warning" title="Emitente em produção">
                    Ativado em {{ $this->emitente->producao_ativada_em?->format('d/m/Y H:i') }}.
                    Todo CT-e e MDF-e emitido tem valor fiscal.
                </x-ui.alert>
                <div class="mt-4">
                    <x-ui.button variant="secondary" wire:click="voltarParaHomologacao">Voltar para homologação</x-ui.button>
                </div>
            @endif
        </x-ui.card>
    @endcan

    {{-- Histórico --}}
    <x-ui.card title="Histórico" subtitle="Certificados anteriores permanecem registrados. Nenhum é apagado." :padded="false">
        @if ($this->certificados->isEmpty())
            <x-ui.empty-state class="m-5" title="Sem histórico" />
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200">
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Titular</th>
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Validade</th>
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Enviado por</th>
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->certificados as $cert)
                        <tr class="border-b border-graphite-100">
                            <td class="px-2 py-2">{{ $cert->titular }}</td>
                            <td class="num px-2 py-2">{{ $cert->valido_de?->format('d/m/Y') }} a {{ $cert->valido_ate->format('d/m/Y') }}</td>
                            <td class="px-2 py-2">{{ $cert->enviadoPor?->name ?? '-' }}</td>
                            <td class="px-2 py-2">
                                @if ($cert->ativo)
                                    <span class="etiqueta bg-success-100 px-2 py-1 text-success-800">Ativo</span>
                                @elseif ($cert->vencido())
                                    <span class="etiqueta bg-graphite-100 px-2 py-1 text-graphite-600">Vencido</span>
                                @else
                                    <span class="etiqueta bg-graphite-100 px-2 py-1 text-graphite-600">Substituído</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
