@props(['icon', 'color' => 'blue', 'label' => null])

@php
$colors = [
    'blue' => 'bg-blue-50 text-blue-600 hover:bg-blue-100',
    'amber' => 'bg-amber-50 text-amber-600 hover:bg-amber-100',
    'red' => 'bg-red-50 text-red-600 hover:bg-red-100',
];
$classes = $colors[$color] ?? $colors['blue'];
@endphp

<button type="button" {{ $attributes->merge(['class' => "w-8 h-8 rounded-full flex items-center justify-center transition-colors $classes"]) }} @if($label) title="{{ __($label) }}" @endif>
    <x-icon :name="$icon" class="w-4 h-4" />
</button>
