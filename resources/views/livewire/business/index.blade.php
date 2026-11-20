<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow overflow-hidden divide-y">
        @forelse($items as $item)
            <a href="{{ route($item['route']) }}" class="flex items-center gap-4 p-4 hover:bg-slate-50">
                <div @class([
                    'w-11 h-11 rounded-xl flex items-center justify-center text-white shrink-0',
                    'bg-blue-600' => $item['color'] === 'blue',
                    'bg-green-600' => $item['color'] === 'green',
                    'bg-purple-600' => $item['color'] === 'purple',
                    'bg-orange-500' => $item['color'] === 'orange',
                    'bg-red-600' => $item['color'] === 'red',
                    'bg-indigo-600' => $item['color'] === 'indigo',
                ])>
                    <x-icon :name="$item['icon']" class="w-5 h-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-medium truncate">{{ $item['title'] }}</p>
                    <p class="text-sm text-slate-500 truncate">{{ $item['description'] }}</p>
                </div>
                <x-icon name="chevron-right" class="w-5 h-5 text-slate-300 shrink-0" />
            </a>
        @empty
            <p class="p-8 text-center text-slate-400">Nothing to show here yet.</p>
        @endforelse
    </div>
</div>
