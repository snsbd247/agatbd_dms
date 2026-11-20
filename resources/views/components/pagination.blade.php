@props(['paginator'])

@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $window = 2;

    $pages = collect(range(max(1, $current - $window), min($last, $current + $window)))
        ->when($current - $window > 1, fn ($c) => $c->prepend('...')->prepend(1))
        ->when($current + $window < $last, fn ($c) => $c->push('...')->push($last))
        ->unique()
        ->values();
@endphp

@if($paginator->hasPages())
    <div class="flex items-center justify-between mt-4 flex-wrap gap-3">
        <p class="text-sm text-slate-500">
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} entries
        </p>
        <div class="flex items-center gap-1.5">
            <button
                type="button"
                wire:click="previousPage"
                @if($current === 1) disabled @endif
                class="w-9 h-9 rounded-lg border flex items-center justify-center text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <x-icon name="chevron-left" class="w-4 h-4" />
            </button>

            @foreach($pages as $page)
                @if($page === '...')
                    <span class="w-9 h-9 flex items-center justify-center text-slate-400">...</span>
                @else
                    <button
                        type="button"
                        wire:click="gotoPage({{ $page }})"
                        class="w-9 h-9 rounded-lg border flex items-center justify-center text-sm font-medium {{ $page === $current ? 'bg-indigo-600 border-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50' }}"
                    >
                        {{ $page }}
                    </button>
                @endif
            @endforeach

            <button
                type="button"
                wire:click="nextPage"
                @if($current === $last) disabled @endif
                class="w-9 h-9 rounded-lg border flex items-center justify-center text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <x-icon name="chevron-right" class="w-4 h-4" />
            </button>
        </div>
    </div>
@endif
