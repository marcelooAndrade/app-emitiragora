@props(['title', 'eyebrow' => null, 'description' => null])

{{-- Visual de 09/10/2026: título grande escrito como se fala, sem filete
     embaixo; o assunto (eyebrow) vira uma linha discreta acima dele. --}}
<header {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-4']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-[13px] font-medium text-graphite-500">{{ $eyebrow }}</p>
        @endif
        <h1 class="display-title mt-0.5 text-3xl text-graphite-900">{{ $title }}</h1>
        @if ($description)
            <p class="mt-2 max-w-2xl text-sm text-graphite-500">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
