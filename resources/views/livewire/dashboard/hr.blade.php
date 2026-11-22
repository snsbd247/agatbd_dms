<div class="space-y-5">
    @php $money = fn ($v) => '৳ '.number_format($v, 2); @endphp
    @include('livewire.dashboard.partials.tabs', ['current' => 'dashboard.hr'])
    <div><h2 class="text-2xl font-bold text-slate-800">{{ __('HR Dashboard') }}</h2><p class="mt-1 text-sm text-slate-500">{{ __('Staff, today\'s attendance, leave, salary and loans.') }}</p></div>

    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card icon="user-group" color="blue" label="Active employees" :value="$stats['employees']" :sublabel="collect(\App\Models\Hr\Employee::CATEGORIES)->map(fn ($l, $k) => __($l).' '.($stats['by_category'][$k] ?? 0))->implode(' · ')" />
        <x-stat-card icon="shield-check" color="green" label="Present today" :value="$stats['present'].' / '.$stats['tracked']" :sublabel="__('Late').': '.$stats['late']" />
        <x-stat-card icon="calendar" color="purple" label="On leave today" :value="$stats['on_leave']" :sublabel="__('Not in yet / absent').': '.$stats['not_in']" />
        <x-stat-card icon="credit-card" color="orange" label="Advances & loans outstanding" :value="$money($stats['loans_out'])" :sublabel="__(':n open', ['n' => $stats['loans_open']])" />
    </section>

    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <a href="{{ route('hr.leaves') }}" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:shadow-md"><p class="text-xs text-slate-500">{{ __('Leave applications waiting') }}</p><p class="mt-1 text-2xl font-bold {{ $stats['leave_pending'] ? 'text-amber-600' : 'text-slate-800' }}">{{ $stats['leave_pending'] }}</p></a>
        <a href="{{ $run ? route('hr.payroll.show', $run) : route('hr.payroll.index') }}" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:shadow-md"><p class="text-xs text-slate-500">{{ __('Salary sheet') }} {{ $run?->period }}</p><p class="mt-1 text-lg font-bold text-slate-800">{{ $run ? __(ucfirst($run->status)).' · '.$money($runTotal) : __('Not made yet') }}</p></a>
        <a href="{{ route('hr.employees.index') }}" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:shadow-md"><p class="text-xs text-slate-500">{{ __('Joined this month') }}</p><p class="mt-1 text-2xl font-bold text-slate-800">{{ $stats['joined'] }}</p></a>
        <a href="{{ route('hr.devices') }}" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:shadow-md"><p class="text-xs text-slate-500">{{ __('Attendance machines online') }}</p><p class="mt-1 text-2xl font-bold {{ $stats['devices'] && $stats['devices_online'] < $stats['devices'] ? 'text-rose-600' : 'text-slate-800' }}">{{ $stats['devices_online'] }} / {{ $stats['devices'] }}</p></a>
    </section>

    <section class="grid gap-5 xl:grid-cols-2">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-t-2 border-t-amber-500 px-5 py-3"><h3 class="font-semibold text-slate-800">{{ __('Late today') }}</h3><a href="{{ route('hr.attendance') }}" class="text-sm text-indigo-600 hover:underline">{{ __('Attendance') }}</a></div>
            <div class="divide-y">
                @forelse($lateToday as $a)
                    <div class="flex justify-between px-5 py-3 text-sm"><span class="text-slate-700">{{ $a->employee?->name }}</span><span class="font-medium text-amber-700">{{ $a->in_time ? \Illuminate\Support\Carbon::parse($a->in_time)->format('h:i A') : '' }}</span></div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-400">{{ __('No one late today.') }}</p>
                @endforelse
            </div>
        </div>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-t-2 border-t-purple-500 px-5 py-3"><h3 class="font-semibold text-slate-800">{{ __('Leave applications waiting') }}</h3><a href="{{ route('hr.leaves') }}" class="text-sm text-indigo-600 hover:underline">{{ __('Leave') }}</a></div>
            <div class="divide-y">
                @forelse($leaves as $l)
                    <div class="flex justify-between gap-3 px-5 py-3 text-sm"><span class="text-slate-700">{{ $l->employee?->name }} <span class="text-xs text-slate-400">{{ __($l->leaveType?->name ?? '') }}</span></span><span class="text-slate-500">{{ $l->from_date?->format('d M') }} – {{ $l->to_date?->format('d M') }}</span></div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-400">{{ __('Nothing waiting.') }}</p>
                @endforelse
            </div>
        </div>
    </section>
</div>
