@props(['rows' => 4])

<textarea rows="{{ $rows }}" {{ $attributes->merge([
    'class' => 'min-w-0 rounded-md border border-graphite-200 bg-white px-4 py-3 text-sm text-graphite-900 '
        .'placeholder:text-graphite-400 hover:border-graphite-300 focus:border-primary-600 focus:outline-none focus:ring-4 '
        .'focus:ring-primary-600/15',
]) }}>{{ $slot }}</textarea>
