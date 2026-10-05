<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Contabilidade"
        title="Pacote da contabilidade"
        description="Tudo que o contador precisa para escriturar o período, organizado por pasta." />

    @error('periodo')
        <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.card title="Período">
        <form wire:submit="baixar" class="grid gap-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="De" for="c-de" required>
                    <x-ui.input id="c-de" type="date" wire:model.live="de" />
                </x-ui.field>
                <x-ui.field label="Até" for="c-ate" required>
                    <x-ui.input id="c-ate" type="date" wire:model.live="ate" />
                </x-ui.field>
            </div>

            <div class="grid grid-cols-2 gap-px border border-graphite-200 bg-graphite-200 sm:grid-cols-4">
                @foreach ([
                    'CT-e autorizados' => $this->previa['autorizados'],
                    'CT-e cancelados' => $this->previa['cancelados'],
                    'MDF-e' => $this->previa['mdfes'],
                ] as $rotulo => $valor)
                    <div class="bg-white p-3">
                        <span class="num display-title block text-2xl">{{ $valor }}</span>
                        <span class="etiqueta text-graphite-500">{{ $rotulo }}</span>
                    </div>
                @endforeach
                <div class="bg-white p-3">
                    <span class="num display-title block text-2xl">{{ App\Support\Dinheiro::formatar($this->previa['frete_centavos']) }}</span>
                    <span class="etiqueta text-graphite-500">Frete</span>
                </div>
            </div>

            <div><x-ui.button type="submit" size="lg">Baixar pacote</x-ui.button></div>
        </form>
    </x-ui.card>

    <x-ui.card title="O que vai no pacote">
        <dl class="grid gap-2 text-sm">
            @foreach ([
                'cte/' => 'XML autorizado dos CT-e (receita de frete)',
                'cte-cancelados/' => 'XML dos CT-e que foram cancelados depois',
                'mdfe/' => 'XML autorizado dos MDF-e',
                'resumo.csv' => 'Planilha com uma linha por CT-e, com o protocolo de cancelamento quando houver',
            ] as $pasta => $desc)
                <div class="flex flex-wrap gap-x-3 border-b border-graphite-100 pb-1">
                    <dt class="num w-48 font-semibold text-graphite-900">{{ $pasta }}</dt>
                    <dd class="text-graphite-600">{{ $desc }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-4 text-xs text-graphite-500">
            O XML é o documento fiscal. O DACTE e o DAMDFE são apenas a representação impressa e não o substituem.
        </p>
    </x-ui.card>
</div>
