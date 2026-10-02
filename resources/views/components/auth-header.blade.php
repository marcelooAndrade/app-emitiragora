@props([
    'title',
    'description',
])

{{-- Alinhado à esquerda: no layout dividido o formulário é uma coluna de
     leitura, e o título centralizado ficava solto sobre campos alinhados à
     esquerda. --}}
<div class="flex w-full flex-col">
    <span class="mb-4 w-fit rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700 ring-1 ring-primary-100">Área segura</span>
    <flux:heading size="xl" level="1" class="text-3xl! font-bold! tracking-tight">{{ $title }}</flux:heading>
    <flux:subheading class="mt-2">{{ $description }}</flux:subheading>
</div>
