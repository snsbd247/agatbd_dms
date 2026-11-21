@php $hasQuery = mb_strlen(trim($q)) >= 2; @endphp

@if($page)
    <div class="max-w-2xl">
        <div class="relative mb-4">
            <x-icon name="search" class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input wire:model.live.debounce.300ms="q" type="search" autofocus placeholder="Search orders, retailers, products, invoices…" class="w-full pl-10 pr-3 py-3 border rounded-xl bg-white text-base">
        </div>

        @if(! $hasQuery)
            <p class="text-sm text-slate-400 text-center py-8">Type at least 2 letters to search.</p>
        @elseif($groups === [])
            <p class="text-sm text-slate-400 text-center py-8">Nothing found for “{{ $q }}”.</p>
        @else
            <div class="space-y-4">
                @foreach($groups as $group)
                    <div class="bg-white rounded-xl shadow overflow-hidden">
                        <p class="px-4 py-2 text-xs font-semibold text-slate-500 bg-slate-50 flex items-center gap-2"><x-icon :name="$group['icon']" class="w-4 h-4" /> {{ $group['label'] }}</p>
                        @foreach($group['items'] as $item)
                            <a href="{{ $item['url'] }}" class="block px-4 py-3 border-t hover:bg-slate-50">
                                <p class="text-sm font-medium">{{ $item['title'] }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ $item['subtitle'] }}</p>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@else
    <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
        <x-icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
        <input
            wire:model.live.debounce.300ms="q"
            @focus="open = true"
            @input="open = true"
            type="search"
            placeholder="Search anything..."
            class="pl-9 pr-3 py-2 border rounded-lg text-sm w-56 lg:w-72 bg-slate-50 focus:bg-white"
            autocomplete="off"
        >
        @if($hasQuery)
            <div x-show="open" x-cloak class="absolute right-0 mt-2 w-96 max-w-[90vw] bg-white rounded-xl shadow-2xl border z-50 max-h-[70vh] overflow-y-auto">
                @forelse($groups as $group)
                    <p class="px-4 pt-3 pb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400 flex items-center gap-1.5"><x-icon :name="$group['icon']" class="w-3.5 h-3.5" /> {{ $group['label'] }}</p>
                    @foreach($group['items'] as $item)
                        <a href="{{ $item['url'] }}" class="block px-4 py-2 hover:bg-indigo-50">
                            <p class="text-sm font-medium">{{ $item['title'] }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ $item['subtitle'] }}</p>
                        </a>
                    @endforeach
                @empty
                    <p class="px-4 py-6 text-center text-sm text-slate-400">Nothing found for “{{ $q }}”.</p>
                @endforelse
            </div>
        @endif
    </div>
@endif
