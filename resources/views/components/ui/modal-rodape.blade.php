{{-- Rodapé da `x-ui.modal`: ações à direita, a principal por último. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-end gap-2 border-t border-graphite-100 bg-graphite-50/60 px-6 py-4']) }}>{{ $slot }}</div>
