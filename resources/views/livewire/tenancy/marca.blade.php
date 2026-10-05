<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Identidade"
        title="Marca"
        :description="$this->tenant->nome" />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    @if ($avisoContraste)
        <x-ui.alert variant="warning" title="Cor escurecida para garantir leitura">
            A cor informada não alcançava o contraste mínimo com texto branco, então foi escurecida
            o mínimo necessário. A cor original continua presente nos tons claros da escala.
        </x-ui.alert>
    @endif

    <x-ui.card title="Cores" subtitle="Todo o sistema usa variáveis CSS, então salvar aqui repinta as telas inteiras.">
        <form wire:submit="salvar" class="grid gap-5">

            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.field label="Nome curto" for="m-nome" hint="Aparece na barra lateral.">
                    <x-ui.input id="m-nome" wire:model="nomeCurto" maxlength="40" />
                </x-ui.field>

                <x-ui.field label="Cor da marca" for="m-prim" required :error="$errors->first('primaria')"
                    hint="A cor de ação e de destaque.">
                    <div class="flex gap-2">
                        <input type="color" id="m-prim" wire:model.live="primaria"
                            class="h-[38px] w-14 shrink-0 cursor-pointer border border-graphite-300 bg-white p-1">
                        <x-ui.input wire:model.live.debounce.400ms="primaria" class="flex-1" />
                    </div>
                </x-ui.field>

                <x-ui.field label="Cor escura" for="m-neut" required
                    hint="O preto da marca. Vira a barra lateral e o texto.">
                    <div class="flex gap-2">
                        <input type="color" id="m-neut" wire:model.live="neutra"
                            class="h-[38px] w-14 shrink-0 cursor-pointer border border-graphite-300 bg-white p-1">
                        <x-ui.input wire:model.live.debounce.400ms="neutra" class="flex-1" />
                    </div>
                </x-ui.field>
            </div>

            @if ($this->contrastePrimaria !== null)
                <div class="flex flex-wrap items-center gap-3 border-l-2 border-graphite-200 pl-4 text-sm">
                    <span class="text-graphite-600">Contraste com o texto que vai sobre ela</span>
                    <span class="inline-flex items-center gap-1.5 border border-graphite-200 px-2 py-0.5">
                        <span class="size-3 border border-graphite-300" style="background-color: {{ $this->textoSobrePrimaria }}"></span>
                        <span class="num text-xs text-graphite-500">{{ $this->textoSobrePrimaria }}</span>
                    </span>
                    <span class="text-graphite-600">:</span>
                    <span class="num font-semibold {{ $this->contrastePrimaria >= 4.5 ? 'text-success-700' : 'text-ember-700' }}">
                        {{ number_format($this->contrastePrimaria, 2, ',', '.') }}
                    </span>
                    @if ($this->contrastePrimaria >= 4.5)
                        <span class="etiqueta bg-success-100 px-2 py-1 text-success-800">Passa em WCAG AA</span>
                    @else
                        <span class="etiqueta bg-ember-100 px-2 py-1 text-ember-800">Nenhum texto lê sobre ela: será escurecida ao salvar</span>
                    @endif
                </div>
            @endif

            <div><x-ui.button type="submit" size="lg">Salvar marca</x-ui.button></div>
        </form>
    </x-ui.card>

    {{-- Logos. São duas de propósito: o tenant é a empresa que assina o
         sistema, o emitente é o CNPJ que assina o CT-e. Matriz e filial
         dividem a primeira e podem imprimir logos diferentes na segunda. --}}
    <x-ui.card title="Logos" subtitle="Uma identifica o sistema na tela, a outra sai impressa no DACTE e no DAMDFE. Não se substituem.">
        <div class="grid gap-8 md:grid-cols-2">

            <form wire:submit="salvarLogoSistema" class="grid min-w-0 content-start gap-3">
                <div>
                    <h3 class="text-xs font-semibold text-graphite-900">Logo do sistema</h3>
                    <p class="mt-1 text-xs text-graphite-600">
                        Aparece na barra lateral, no lugar do quadrado com a inicial.
                    </p>
                </div>

                {{-- A prévia vai sobre grafite porque é sobre grafite que a
                     logo vai viver. Logo escura some na sidebar, e é melhor
                     descobrir isso aqui do que depois de salvar. --}}
                <div class="flex h-20 items-center justify-center border border-graphite-300 bg-graphite-900 px-4">
                    @if ($this->tenant?->logo_path)
                        <img src="{{ route('logo') }}" alt="Logo do sistema" class="max-h-12 w-auto object-contain">
                    @else
                        <span class="text-xs text-graphite-400">Nenhuma logo enviada</span>
                    @endif
                </div>

                <x-ui.field label="Arquivo" for="logo-sistema" :error="$errors->first('logoSistema')"
                    hint="PNG ou JPG, até 1 MB. Fundo transparente fica melhor.">
                    <input type="file" id="logo-sistema" wire:model="logoSistema" accept="image/png,image/jpeg"
                        class="w-full min-w-0 rounded-md border border-graphite-300 bg-white p-2 text-xs text-graphite-700 file:mr-3 file:cursor-pointer file:rounded file:border-0 file:bg-graphite-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-graphite-900">
                </x-ui.field>

                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="submit" size="sm" wire:loading.attr="disabled" wire:target="logoSistema,salvarLogoSistema">
                        Enviar
                    </x-ui.button>
                    @if ($this->tenant?->logo_path)
                        <x-ui.button type="button" variant="ghost" size="sm"
                            wire:click="removerLogoSistema"
                            wire:confirm="Remover a logo do sistema? A barra lateral volta a mostrar a inicial.">
                            Remover
                        </x-ui.button>
                    @endif
                </div>
            </form>

            <form wire:submit="salvarLogoDocumento" class="grid min-w-0 content-start gap-3">
                <div>
                    <h3 class="text-xs font-semibold text-graphite-900">Logo dos documentos</h3>
                    <p class="mt-1 text-xs text-graphite-600">
                        Impressa no DACTE e no DAMDFE do emitente
                        <span class="font-medium text-graphite-900">{{ $this->emitente?->nome_fantasia ?: $this->emitente?->razao_social ?: 'em foco' }}</span>.
                    </p>
                </div>

                {{-- Aqui o fundo é branco porque o DACTE e o DAMDFE são impressos em papel. --}}
                <div class="flex h-20 items-center justify-center border border-graphite-300 bg-white px-4">
                    @if ($this->emitente?->logo_path)
                        <span class="etiqueta bg-success-100 px-2 py-1 text-success-800">Logo definida</span>
                    @else
                        <span class="text-xs text-graphite-500">Nenhuma logo enviada</span>
                    @endif
                </div>

                <x-ui.field label="Arquivo" for="logo-documento" :error="$errors->first('logoDocumento')"
                    hint="PNG ou JPG, até 1 MB. O DACTE e o DAMDFE imprimem em preto e branco.">
                    <input type="file" id="logo-documento" wire:model="logoDocumento" accept="image/png,image/jpeg"
                        class="w-full min-w-0 rounded-md border border-graphite-300 bg-white p-2 text-xs text-graphite-700 file:mr-3 file:cursor-pointer file:rounded file:border-0 file:bg-graphite-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-graphite-900">
                </x-ui.field>

                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="submit" size="sm" wire:loading.attr="disabled" wire:target="logoDocumento,salvarLogoDocumento">
                        Enviar
                    </x-ui.button>
                    @if ($this->emitente?->logo_path)
                        <x-ui.button type="button" variant="ghost" size="sm"
                            wire:click="removerLogoDocumento"
                            wire:confirm="Remover a logo dos documentos? As próximas vias saem sem ela.">
                            Remover
                        </x-ui.button>
                    @endif
                </div>
            </form>

        </div>
    </x-ui.card>

    {{-- Prévia --}}
    <x-ui.card title="Prévia da escala" subtitle="Onze tons gerados a partir das duas cores. A marca fica no tom 600, o escuro no 900.">
        <div class="grid gap-5">
            @foreach ([['Cor da marca', $this->previaPrimaria, 600], ['Cor escura', $this->previaNeutra, 900]] as [$rotulo, $escala, $ancora])
                @if ($escala)
                    <div>
                        <h3 class="mb-2 text-xs font-semibold text-graphite-900">{{ $rotulo }}</h3>
                        <div class="grid grid-cols-6 gap-1 sm:grid-cols-11">
                            @foreach ($escala as $tom => $cor)
                                <div class="grid gap-1">
                                    <div class="h-10 ring-1 ring-black/15" style="background-color: {{ $cor }}"></div>
                                    <span class="num text-[10px] {{ $tom === $ancora ? 'font-bold text-primary-700' : 'text-graphite-500' }}">
                                        {{ $tom }}
                                    </span>
                                    <span class="num text-[9px] leading-none text-graphite-400">{{ $cor }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </x-ui.card>
</div>
