<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow overflow-hidden divide-y">
        @foreach($reports as $report)
            <a href="{{ route($report['route']) }}" class="flex items-center gap-4 p-4 hover:bg-slate-50">
                <div @class([
                    'w-11 h-11 rounded-xl flex items-center justify-center text-white shrink-0',
                    'bg-blue-600' => $report['color'] === 'blue',
                    'bg-green-600' => $report['color'] === 'green',
                    'bg-purple-600' => $report['color'] === 'purple',
                    'bg-orange-500' => $report['color'] === 'orange',
                    'bg-red-600' => $report['color'] === 'red',
                    'bg-indigo-600' => $report['color'] === 'indigo',
                    'bg-teal-600' => $report['color'] === 'teal',
                    'bg-cyan-600' => $report['color'] === 'cyan',
                    'bg-pink-600' => $report['color'] === 'pink',
                ])>
                    <x-icon :name="$report['icon']" class="w-5 h-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-medium truncate">{{ __($report['title']) }}</p>
                    <p class="text-sm text-slate-500 truncate">{{ __($report['description']) }}</p>
                </div>
                <x-icon name="chevron-right" class="w-5 h-5 text-slate-300 shrink-0" />
            </a>
        @endforeach
    </div>
</div>
