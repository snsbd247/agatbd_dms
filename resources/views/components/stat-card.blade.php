@props(['icon', 'color' => 'blue', 'label', 'value', 'sublabel' => null])

@php
$colors = [
    'blue' => 'bg-blue-600',
    'green' => 'bg-green-600',
    'orange' => 'bg-orange-500',
    'purple' => 'bg-purple-600',
    'red' => 'bg-red-600',
    'indigo' => 'bg-indigo-600',
    'teal' => 'bg-teal-600',
    'cyan' => 'bg-cyan-600',
    'pink' => 'bg-pink-600',
];
$bg = $colors[$color] ?? $colors['blue'];
@endphp

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl shadow p-3 sm:p-4 flex items-center gap-2.5 sm:gap-4']) }}>
    <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-xl {{ $bg }} text-white flex items-center justify-center shrink-0">
        <x-icon :name="$icon" class="w-4.5 h-4.5 sm:w-6 sm:h-6" />
    </div>
    <div class="min-w-0">
        <p class="text-slate-500 text-xs sm:text-sm leading-tight break-words">{{ $label }}</p>
        <p class="text-lg sm:text-2xl font-bold leading-tight">{{ $value }}</p>
        @if($sublabel)
            <p class="text-slate-400 text-[11px] sm:text-xs mt-0.5 hidden sm:block">{{ $sublabel }}</p>
        @endif
    </div>
</div>
