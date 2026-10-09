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
        <x-ui.indicador rotulo="Frete no mês" detalhe="CT-e autorizados, sem os cancelados" tom="sucesso"
            icone="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z">
            {{ App\Support\Dinheiro::formatar($mes['frete_centavos']) }}
        </x-ui.indicador>

        <x-ui.indicador rotulo="CT-e no mês" :detalhe="$mes['cancelados'].' cancelado(s)'"
            icone="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z">
            {{ number_format($mes['ctes'], 0, ',', '.') }}
        </x-ui.indicador>

        <x-ui.indicador rotulo="Viagens em aberto" detalhe="Ainda sem CT-e emitido" tom="neutro"
            icone="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z">
            {{ number_format($mes['viagens_abertas'], 0, ',', '.') }}
        </x-ui.indicador>

        @php
            $semCertificado = $this->certificado === null;
            $certificadoVencido = ! $semCertificado && $this->diasDeCertificado < 0;
            $certificadoPerto = ! $semCertificado && ! $certificadoVencido && $this->diasDeCertificado <= 30;
        @endphp
        <x-ui.indicador rotulo="Certificado"
            :tom="$semCertificado || $certificadoVencido ? 'perigo' : ($certificadoPerto ? 'alerta' : 'sucesso')"
            :valorClasse="$semCertificado || $certificadoVencido ? 'text-danger-700' : ($certificadoPerto ? 'text-ember-700' : 'text-graphite-900')"
            :detalhe="$semCertificado ? 'Necessário para transmitir' : ($certificadoVencido ? 'Desde '.$this->certificado->valido_ate->format('d/m/Y') : 'dia(s) até vencer')"
            icone="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z">
            {{ $semCertificado ? 'Nenhum' : ($certificadoVencido ? 'vencido' : $this->diasDeCertificado) }}
        </x-ui.indicador>
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
