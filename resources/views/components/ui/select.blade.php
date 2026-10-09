@props([])

<select {{ $attributes->merge([
    'class' => 'min-h-11 min-w-0 rounded-md border border-graphite-200 bg-white px-4 text-sm text-graphite-900 '
        .'hover:border-graphite-300 focus:border-primary-600 focus:outline-none focus:ring-4 '
        .'focus:ring-primary-600/15 disabled:bg-graphite-50',
]) }}>
    {{ $slot }}
</select>
