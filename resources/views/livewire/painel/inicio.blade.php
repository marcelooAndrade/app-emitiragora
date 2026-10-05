<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Visão geral"
        title="Painel"
        description="O que exige ação vem antes do que já aconteceu." />

    {{-- Pendências primeiro. Quem abre o sistema de manhã precisa saber o que
         travou ontem antes de saber quanto faturou no mês. --}}
    @if ($this->certificado === null)
        <x-ui.alert variant="danger" title="Nenhum certificado digital cadastrado">
            Sem certificado A1 não há assinatura, e sem assinatura a SEFAZ não recebe nada.
            @can('certificado.gerenciar')
                Cadastre em <a href="{{ route('certificados') }}" class="underline">Certificado</a>.
            @endcan
        </x-ui.alert>
    @elseif ($this->diasDeCertificado < 0)
        <x-ui.alert variant="danger" title="O certificado digital venceu">
            Venceu em {{ $this->certificado->valido_ate->format('d/m/Y') }}, há {{ abs($this->diasDeCertificado) }} dia(s).
            Nenhum CT-e nem MDF-e será transmitido até a troca.
        </x-ui.alert>
    @elseif ($this->diasDeCertificado <= 30)
        <x-ui.alert variant="warning" title="O certificado vence em {{ $this->diasDeCertificado }} dia(s)">
            Vence em {{ $this->certificado->valido_ate->format('d/m/Y') }}.
            Renove antes: certificado vencido para a emissão de CT-e e MDF-e por completo.
        </x-ui.alert>
    @endif

    @if ($this->pendentes->isNotEmpty())
        <x-ui.card title="Documentos que pararam no caminho" subtitle="{{ $this->pendentes->count() }} documento(s) aguardando uma decisão sua" :padded="false">
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200 text-left">
                        <th class="etiqueta px-5 py-2 text-graphite-500">Documento</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Viagem</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Situação</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">O que fazer</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->pendentes as $documento)
                        <tr class="border-b border-graphite-100 last:border-0">
                            <td class="num px-5 py-3 font-medium text-graphite-900">
                                {{ $documento instanceof \App\Models\Cte ? 'CT-e' : 'MDF-e' }} {{ $documento->numeroFormatado() }}
                            </td>
                            <td class="px-5 py-3">
                                <a href="{{ route('viagens.detalhe', $documento->viagem_id) }}" wire:navigate class="num text-graphite-900 hover:underline">{{ $documento->viagem?->numeroFormatado() }}</a>
                            </td>
                            <td class="px-5 py-3"><x-ui.badge-status :status="$documento->status" /></td>
                            <td class="px-5 py-3 text-graphite-600">
                                @if ($documento->status->value === 'em_processamento')
                                    Consulte pela chave antes de qualquer coisa. A SEFAZ pode já ter autorizado sem a resposta ter voltado, e transmitir de novo duplicaria.
                                @else
                                    {{ $documento->x_motivo ?: 'Corrija o que a SEFAZ apontou e transmita de novo.' }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @endif

    @if ($this->mdfesParaEncerrar->isNotEmpty())
        <x-ui.alert variant="warning" title="{{ $this->mdfesParaEncerrar->count() }} MDF-e em viagem há mais de {{ \App\Livewire\Painel\Inicio::DIAS_PARA_ENCERRAR_MDFE }} dias">
            Se a viagem já terminou, encerre: a SEFAZ recusa MDF-e novo enquanto houver um aberto há mais de 30 dias.
            @foreach ($this->mdfesParaEncerrar->take(4) as $mdfe)
                <a href="{{ route('viagens.detalhe', $mdfe->viagem_id) }}" wire:navigate class="underline">viagem {{ $mdfe->viagem?->numeroFormatado() }}</a>@if (! $loop->last) · @endif
            @endforeach
        </x-ui.alert>
    @endif

    {{-- Só depois, o retrato do mês. --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.card>
            <p class="etiqueta text-graphite-500">Frete no mês</p>
            <p class="num display-title mt-1 text-3xl text-graphite-900">{{ App\Support\Dinheiro::formatar($mes['frete_centavos']) }}</p>
            <p class="mt-1 text-xs text-graphite-500">CT-e autorizados, sem os cancelados</p>
        </x-ui.card>

        <x-ui.card>
            <p class="etiqueta text-graphite-500">CT-e no mês</p>
            <p class="num display-title mt-1 text-3xl text-graphite-900">{{ number_format($mes['ctes'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-graphite-500">{{ $mes['cancelados'] }} cancelado(s)</p>
        </x-ui.card>

        <x-ui.card>
            <p class="etiqueta text-graphite-500">Viagens em aberto</p>
            <p class="num display-title mt-1 text-3xl text-graphite-900">{{ number_format($mes['viagens_abertas'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-graphite-500">Ainda sem CT-e emitido</p>
        </x-ui.card>

        <x-ui.card>
            <p class="etiqueta text-graphite-500">Certificado</p>
            @if ($this->certificado === null)
                <p class="display-title mt-1 text-3xl text-danger-700">Nenhum</p>
                <p class="mt-1 text-xs text-graphite-500">Necessário para transmitir</p>
            @else
                <p class="num display-title mt-1 text-3xl {{ $this->diasDeCertificado < 0 ? 'text-danger-700' : ($this->diasDeCertificado <= 30 ? 'text-ember-700' : 'text-graphite-900') }}">
                    {{ $this->diasDeCertificado < 0 ? 'vencido' : $this->diasDeCertificado }}
                </p>
                <p class="mt-1 text-xs text-graphite-500">
                    {{ $this->diasDeCertificado < 0 ? 'Desde '.$this->certificado->valido_ate->format('d/m/Y') : 'dia(s) até vencer' }}
                </p>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="Últimas viagens" :padded="false">
        @if ($this->ultimas->isEmpty())
            <x-ui.empty-state
                class="m-5"
                title="Nenhuma viagem ainda"
                description="Quando a primeira viagem for lançada, ela aparece aqui.">
                <x-slot:action>
                    @can('transporte.operar')
                        <x-ui.button href="{{ route('viagens') }}">Nova viagem</x-ui.button>
                    @endcan
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200 text-left">
                        <th class="etiqueta px-5 py-2 text-graphite-500">Viagem</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Carregamento</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Motorista e placa</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->ultimas as $viagem)
                        <tr class="border-b border-graphite-100 last:border-0">
                            <td class="num px-5 py-3 font-medium">
                                <a href="{{ route('viagens.detalhe', $viagem) }}" wire:navigate class="text-graphite-900 hover:underline">{{ $viagem->numeroFormatado() }}</a>
                            </td>
                            <td class="num px-5 py-3 text-graphite-600">{{ $viagem->data_carregamento?->format('d/m/Y') }}</td>
                            <td class="px-5 py-3 text-graphite-700">
                                {{ $viagem->motorista?->nome ?? 'Sem motorista' }}
                                <span class="text-xs text-graphite-500">· {{ $viagem->veiculo?->placaFormatada() ?? 'sem veículo' }}</span>
                            </td>
                            <td class="px-5 py-3"><x-ui.badge-status :status="$viagem->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
