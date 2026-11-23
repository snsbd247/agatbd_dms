{{-- Switch between the dashboards this user can open. Hidden when there is only one. --}}
@php $tabs = \App\Http\Controllers\DashboardController::dashboardsFor(auth()->user()); @endphp
@if(count($tabs) > 1)
    <nav class="flex gap-1 overflow-x-auto rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200">
        @foreach($tabs as $route => $tab)
            <a href="{{ route($route) }}" class="flex shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium {{ ($current ?? null) === $route ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                <x-icon :name="$tab['icon']" class="h-4 w-4" /> {{ __($tab['label']) }}
            </a>
        @endforeach
    </nav>
@endif

{{-- Things waiting for this user (cheques due, VAT, depreciation …). --}}
@php $reminders = \App\Support\Reminders::for(auth()->user()); @endphp
@if($reminders)
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-200 divide-y divide-slate-100" x-data="{ all: false }">
        @foreach($reminders as $i => $r)
            <div class="flex items-center gap-3 px-4 py-2.5 text-sm" @if($i >= 4) x-show="all" x-cloak @endif>
                <span @class(['h-2.5 w-2.5 shrink-0 rounded-full', 'bg-red-500' => $r['level'] === 'red', 'bg-amber-500' => $r['level'] === 'amber', 'bg-blue-500' => $r['level'] === 'blue'])></span>
                <span class="flex-1 text-slate-700">{{ $r['text'] }}</span>
                <a href="{{ $r['href'] }}" class="shrink-0 text-indigo-600 font-medium hover:underline">{{ $r['action'] }}</a>
            </div>
        @endforeach
        @if(count($reminders) > 4)
            <button type="button" x-on:click="all = !all" class="w-full px-4 py-2 text-xs text-slate-500 hover:bg-slate-50" x-text="all ? @js(__('Show fewer')) : @js(__('Show all :n', ['n' => count($reminders)]))"></button>
        @endif
    </div>
@endif
