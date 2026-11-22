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
