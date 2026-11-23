<div class="max-w-3xl space-y-5" x-data="{ q: '' }">
    <input type="search" x-model="q" placeholder="{{ __('Find a page…') }}" class="w-full border rounded-xl px-4 py-2.5 text-sm bg-white">

    @foreach($menu as $section)
        <div x-show="q === '' || $el.innerText.toLowerCase().includes(q.toLowerCase())">
            @if($section['label'] !== '')
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2 px-1">{{ __($section['label']) }}</p>
            @endif
            <div class="grid sm:grid-cols-2 gap-3">
                @foreach($section['groups'] as $group)
                    <div class="bg-white rounded-xl shadow-sm p-3" x-show="q === '' || $el.innerText.toLowerCase().includes(q.toLowerCase())">
                        <p class="flex items-center gap-2 text-sm font-semibold text-slate-700 mb-1.5">
                            <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center"><x-icon :name="$group['icon']" class="w-4 h-4" /></span>
                            {{ __($group['label']) }}
                        </p>
                        <div class="divide-y divide-slate-100">
                            @foreach($group['links'] as $link)
                                <a href="{{ $link['href'] }}" x-show="q === '' || $el.innerText.toLowerCase().includes(q.toLowerCase())"
                                    class="flex items-center justify-between gap-2 py-2 px-1 text-sm {{ $link['active'] ? 'text-indigo-600 font-medium' : 'text-slate-600' }}">
                                    <span class="flex items-center gap-2"><x-icon :name="$link['icon'] ?? 'chevron-right'" class="w-4 h-4 text-slate-400" />{{ __($link['label']) }}</span>
                                    <x-icon name="chevron-right" class="w-4 h-4 text-slate-300" />
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="grid grid-cols-3 gap-3">
        <a href="{{ route('help') }}" class="bg-white rounded-xl shadow-sm p-3 text-center text-xs text-slate-600">
            <x-icon name="book-open" class="w-5 h-5 mx-auto mb-1 text-teal-600" />{{ __('Help & User Guide') }}
        </a>
        <a href="mailto:support@syncsolutionbd.com" class="bg-white rounded-xl shadow-sm p-3 text-center text-xs text-slate-600">
            <x-icon name="headset" class="w-5 h-5 mx-auto mb-1 text-purple-600" />{{ __('Help & Support') }}
        </a>
        <a href="{{ route('more.about') }}" class="bg-white rounded-xl shadow-sm p-3 text-center text-xs text-slate-600">
            <x-icon name="question-mark-circle" class="w-5 h-5 mx-auto mb-1 text-slate-500" />{{ __('About DMS') }}
        </a>
    </div>

    <form method="post" action="{{ route('logout') }}">
        @csrf
        <button class="w-full flex items-center justify-center gap-2 bg-red-50 hover:bg-red-100 text-red-600 font-medium rounded-xl py-3">
            <x-icon name="logout" class="w-5 h-5" /> {{ __('Logout') }}
        </button>
    </form>
</div>
