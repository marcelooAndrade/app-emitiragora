{{-- Miolo da `x-ui.modal`: rola por dentro quando o formulário é alto. --}}
<div {{ $attributes->merge(['class' => 'max-h-[70dvh] overflow-y-auto px-6 py-5']) }}>{{ $slot }}</div>
