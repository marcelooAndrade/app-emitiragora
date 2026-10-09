{{--
    Ação de linha só com ícone, como nas listagens de referência: lápis
    verde para editar, lixeira vermelha para excluir. O rótulo vira `title`
    e `aria-label`, porque o ícone sozinho não é lido por leitor de tela.

    $acao: editar | excluir | reativar | ver.
--}}
@props(['acao', 'rotulo'])

@php
    $icones = [
        'editar' => ['m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10', 'text-success-700 hover:bg-success-50'],
        'excluir' => ['m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0', 'text-danger-600 hover:bg-danger-50'],
        'reativar' => ['M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99', 'text-steel-700 hover:bg-steel-50'],
        'ver' => ['M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z', 'text-graphite-600 hover:bg-graphite-100'],
    ];
    [$caminho, $cores] = $icones[$acao] ?? $icones['ver'];
@endphp

<button type="button" title="{{ $rotulo }}" aria-label="{{ $rotulo }}"
    {{ $attributes->merge(['class' => 'inline-flex size-9 items-center justify-center rounded-full outline-none transition-colors focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-40 '.$cores]) }}>
    <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $caminho }}" />
    </svg>
</button>
