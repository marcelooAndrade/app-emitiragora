{{-- "+ Novo ..." das listagens: abre a janela de cadastro. --}}
<x-ui.button {{ $attributes }}>
    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
    </svg>
    {{ $slot }}
</x-ui.button>
