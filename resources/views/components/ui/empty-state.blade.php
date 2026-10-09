@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-dashed border-graphite-200 bg-graphite-50/60 px-6 py-14 text-center']) }}>
    <p class="display-title text-lg text-graphite-800">{{ $title }}</p>
    @if ($description)
        <p class="mx-auto mt-2 max-w-md text-sm text-graphite-500">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-6 flex justify-center">{{ $action }}</div>
    @endisset
</div>
