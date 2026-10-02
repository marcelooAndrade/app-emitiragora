@php
    use App\Enums\Transporte\CteStatus;
    use App\Support\Dinheiro;
@endphp

<div class="grid gap-6">
    <x-ui.page-header
        eyebrow="Transporte"
        title="Viagens"
        description="Cada viagem junta as NF-e da carga, o motorista e o veículo. Dela saem os CT-e, o MDF-e e a fatura do frete.">
        <x-slot:actions>
            @can('transporte.operar')
                <x-ui.button wire:click="nova">Nova viagem</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav class="flex flex-wrap gap-1" aria-label="Filtrar por situação">
            <button type="button" wire:click="$set('status', '')" @class(['rounded-md px-3 py-1.5 text-xs font-semibold', 'bg-graphite-900 text-white' => $status === '', 'text-graphite-600 hover:bg-graphite-100' => $status !== ''])>
                Todas <span class="num">{{ $this->contagem->sum() }}</span>
            </button>
            @foreach ($situacoes as $situacao)
                <button type="button" wire:click="$set('status', '{{ $situacao->value }}')" @class(['rounded-md px-3 py-1.5 text-xs font-semibold', 'bg-graphite-900 text-white' => $status === $situacao->value, 'text-graphite-600 hover:bg-graphite-100' => $status !== $situacao->value])>
                    {{ $situacao->rotulo() }} <span class="num">{{ $this->contagem[$situacao->value] ?? 0 }}</span>
                </button>
            @endforeach
        </nav>
        <div class="w-full sm:w-72">
            <x-ui.input type="search" wire:model.live.debounce.400ms="busca" placeholder="Número, placa, motorista ou NF-e" aria-label="Buscar viagem" />
        </div>
    </div>

    <x-ui.card :padded="false">
        @if ($this->viagens->isEmpty())
            <div class="p-5">
                <x-ui.empty-state title="Nenhuma viagem por aqui" description="Abra uma viagem e solte os XML das NF-e da carga. O resto o sistema monta.">
                    @can('transporte.operar')
                        <x-slot:action>
                            <x-ui.button wire:click="nova">Nova viagem</x-ui.button>
                        </x-slot:action>
                    @endcan
                </x-ui.empty-state>
            </div>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200 text-left">
                        <th class="etiqueta px-5 py-2 text-graphite-500">Viagem</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Motorista e placa</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Destinos</th>
                        <th class="etiqueta px-5 py-2 text-right text-graphite-500">Frete</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Documentos</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->viagens as $viagem)
                        @php
                            $validos = $viagem->ctes->reject(fn ($c) => $c->status === CteStatus::Cancelado);
                            $autorizados = $validos->where('status', CteStatus::Autorizado)->count();
                        @endphp
                        <tr class="cursor-pointer border-b border-graphite-100 last:border-0" wire:key="viagem-{{ $viagem->id }}"
                            onclick="Livewire.navigate('{{ route('viagens.detalhe', $viagem) }}')">
                            <td class="px-5 py-3">
                                <a href="{{ route('viagens.detalhe', $viagem) }}" wire:navigate class="font-semibold text-graphite-900 hover:underline">{{ $viagem->numeroFormatado() }}</a>
                                <p class="text-xs text-graphite-500">{{ $viagem->data_carregamento?->format('d/m/Y') }} · {{ $viagem->notas->count() }} NF-e</p>
                            </td>
                            <td class="px-5 py-3 text-graphite-700">
                                {{ $viagem->motorista?->nome ?? 'Sem motorista' }}
                                <p class="text-xs text-graphite-500">{{ $viagem->veiculo?->placaFormatada() ?? 'Sem veículo' }}</p>
                            </td>
                            <td class="px-5 py-3 text-graphite-700">
                                {{ $validos->map(fn ($c) => $c->municipio_fim.'/'.$c->uf_fim)->unique()->take(3)->join(', ') ?: '—' }}
                            </td>
                            <td class="num whitespace-nowrap px-5 py-3 text-right font-semibold text-graphite-900">R$ {{ Dinheiro::formatar((int) $validos->sum('valor_total_centavos')) }}</td>
                            <td class="px-5 py-3 text-xs text-graphite-600">
                                CT-e {{ $autorizados }}/{{ $validos->count() }}
                                · MDF-e {{ $viagem->mdfe?->status->rotulo() ?? 'pendente' }}
                            </td>
                            <td class="px-5 py-3"><x-ui.badge-status :status="$viagem->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
