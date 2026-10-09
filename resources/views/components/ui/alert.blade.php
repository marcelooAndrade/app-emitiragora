@props(['variant' => 'info', 'title' => null])

@php
    $variants = [
        'info' => 'border-steel-200 bg-steel-50 text-steel-800',
        'success' => 'border-success-200 bg-success-50 text-success-800',
        'warning' => 'border-ember-200 bg-ember-50 text-ember-800',
        'danger' => 'border-danger-200 bg-danger-50 text-danger-800',
    ];
@endphp

<div role="alert" {{ $attributes->merge(['class' => 'rounded-lg border px-4 py-3.5 text-sm '.($variants[$variant] ?? $variants['info'])]) }}>
    @if ($title)
        <p class="mb-1 font-semibold">{{ $title }}</p>
    @endif
    {{ $slot }}
</div>
