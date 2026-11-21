<div class="max-w-2xl">
    <div class="grid grid-cols-3 sm:grid-cols-4 gap-4">
        @foreach($items as $item)
            <a href="{{ route($item['route']) }}" class="flex flex-col items-center text-center gap-2">
                <div @class([
                    'w-14 h-14 rounded-full flex items-center justify-center text-white',
                    'bg-blue-600' => $item['color'] === 'blue',
                    'bg-green-600' => $item['color'] === 'green',
                    'bg-purple-600' => $item['color'] === 'purple',
                    'bg-indigo-600' => $item['color'] === 'indigo',
                    'bg-orange-500' => $item['color'] === 'orange',
                    'bg-cyan-600' => $item['color'] === 'cyan',
                    'bg-amber-500' => $item['color'] === 'amber',
                    'bg-slate-500' => $item['color'] === 'slate',
                    'bg-teal-600' => $item['color'] === 'teal',
                ])>
                    <x-icon :name="$item['icon']" class="w-6 h-6" />
                </div>
                <p class="text-xs font-medium text-slate-700 leading-tight">{{ __($item['title']) }}</p>
            </a>
        @endforeach
        <a href="mailto:support@syncsolutionbd.com" class="flex flex-col items-center text-center gap-2">
            <div class="w-14 h-14 rounded-full bg-purple-600 text-white flex items-center justify-center">
                <x-icon name="headset" class="w-6 h-6" />
            </div>
            <p class="text-xs font-medium text-slate-700 leading-tight">Help &amp; Support</p>
        </a>
    </div>

    <form method="post" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button class="w-full flex items-center justify-center gap-2 bg-red-50 hover:bg-red-100 text-red-600 font-medium rounded-xl py-3">
            <x-icon name="logout" class="w-5 h-5" /> {{ __('Logout') }}
        </button>
    </form>
</div>
