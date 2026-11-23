@props(['icon', 'color' => 'blue', 'label' => null, 'href' => null, 'target' => null])

{{-- A round icon button for table rows. With href it is a link (view, edit page, print). --}}
@php
$colors = [
    'blue' => 'bg-blue-50 text-blue-600 hover:bg-blue-100',
    'amber' => 'bg-amber-50 text-amber-600 hover:bg-amber-100',
    'red' => 'bg-red-50 text-red-600 hover:bg-red-100',
    'green' => 'bg-green-50 text-green-600 hover:bg-green-100',
    'slate' => 'bg-slate-100 text-slate-600 hover:bg-slate-200',
];
$classes = "w-8 h-8 rounded-full inline-flex items-center justify-center shrink-0 transition-colors ".($colors[$color] ?? $colors['blue']);
@endphp

@if($href)
    <a href="{{ $href }}" @if($target) target="{{ $target }}" @endif {{ $attributes->merge(['class' => $classes]) }} @if($label) title="{{ __($label) }}" aria-label="{{ __($label) }}" @endif>
        <x-icon :name="$icon" class="w-4 h-4" />
    </a>
@else
    <button type="button" {{ $attributes->merge(['class' => $classes]) }} @if($label) title="{{ __($label) }}" aria-label="{{ __($label) }}" @endif>
        <x-icon :name="$icon" class="w-4 h-4" />
    </button>
@endif
