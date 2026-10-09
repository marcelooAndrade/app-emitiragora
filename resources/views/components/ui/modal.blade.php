{{--
    Janela sobre a tela, no padrão das listagens de 09/10/2026: a lista fica
    na largura toda e cadastrar ou editar abre aqui, sem sair dela.

    Quem abre e fecha é o componente Livewire (uma propriedade booleana); a
    janela só existe no HTML quando está aberta. Fecha no X, no fundo
    escurecido e no Esc, sempre chamando o método `$fechar` do componente,
    para o estado do formulário ser limpo num lugar só.

    Use `x-ui.modal-corpo` e `x-ui.modal-rodape` dentro do slot, com o
    `<form>` envolvendo os dois quando houver formulário.
--}}
@props(['titulo', 'subtitulo' => null, 'fechar' => 'fecharFormulario', 'largura' => '2xl'])

@php
    $larguras = ['md' => 'max-w-md', 'lg' => 'max-w-lg', 'xl' => 'max-w-xl', '2xl' => 'max-w-2xl', '3xl' => 'max-w-3xl', '4xl' => 'max-w-4xl'];
@endphp

<div class="fixed inset-0 z-50 flex items-end justify-center overflow-y-auto bg-graphite-950/50 p-3 sm:items-center sm:p-6"
     role="dialog" aria-modal="true" aria-labelledby="modal-titulo"
     wire:click.self="{{ $fechar }}"
     x-data
     x-on:keydown.escape.window="$wire.{{ $fechar }}()"
     x-init="$nextTick(() => $el.querySelector('input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea')?.focus())">
    <div {{ $attributes->merge(['class' => 'my-auto w-full overflow-hidden rounded-xl bg-white shadow-flutuante '.($larguras[$largura] ?? $larguras['2xl'])]) }}>
        <header class="flex items-start justify-between gap-4 border-b border-graphite-100 px-6 py-5">
            <div class="min-w-0">
                <h2 id="modal-titulo" class="display-title text-xl text-graphite-900">{{ $titulo }}</h2>
                @if ($subtitulo)
                    <p class="mt-0.5 text-xs text-graphite-500">{{ $subtitulo }}</p>
                @endif
            </div>
            <button type="button" wire:click="{{ $fechar }}" aria-label="Fechar"
                    class="-mr-2 flex size-9 shrink-0 items-center justify-center rounded-full text-graphite-500 outline-none hover:bg-graphite-100 hover:text-graphite-900 focus-visible:ring-2 focus-visible:ring-primary-500">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </header>
        {{ $slot }}
    </div>
</div>
