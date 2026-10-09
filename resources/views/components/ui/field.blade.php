@props(['label' => null, 'for' => null, 'hint' => null, 'error' => null, 'required' => false])

{{-- `content-start` alinha os campos de uma mesma linha: sem ele, o campo
     que tem dica embaixo distribui a sobra entre as linhas do grid e o
     input sobe alguns pixels em relação aos vizinhos. --}}
<div {{ $attributes->merge(['class' => 'grid content-start gap-1.5']) }}>
    @if ($label)
        <label @if($for) for="{{ $for }}" @endif class="text-[13px] font-semibold text-graphite-800">
            {{ $label }}@if($required)<span class="text-primary-600" aria-hidden="true"> *</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($error)
        <p class="text-xs font-medium text-danger-700">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-xs text-graphite-500">{{ $hint }}</p>
    @endif
</div>
