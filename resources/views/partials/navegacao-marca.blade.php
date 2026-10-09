{{--
    Marca no alto da barra lateral e da gaveta do celular. Com logo enviada
    na tela Marca, vale a logo; sem ela, a inicial sobre a cor da marca e o
    nome. Desde 09/10/2026 o nome sai como foi escrito, não em caixa alta.
--}}
<div class="flex h-[72px] shrink-0 items-center gap-3 px-6">
    @if ($tenant?->logo_path)
        <img src="{{ route('logo') }}" alt="{{ $tenant->nome }}" class="h-7 w-auto max-w-[10rem] object-contain">
    @else
        {{-- A cor do texto vem da marca, não está cravada: sobre marca clara
             escreve-se em grafite, sobre marca escura em branco. Ver
             TemaMarca::textoSobre. --}}
        <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary-600 font-display text-base font-bold text-on-primary">
            {{ mb_strtoupper(mb_substr($tenant?->rotulo() ?? 'E', 0, 1)) }}
        </span>
        <span class="truncate font-display text-lg font-bold tracking-tight text-graphite-900">
            {{ $tenant?->rotulo() ?: 'EmitirAgora' }}
        </span>
    @endif
</div>
