<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap gap-2">
            @foreach(['daily' => 'Daily register', 'import' => 'Import (CSV / ZKTeco)', 'monthly' => 'Month sheet'] as $k => $label)
                <button wire:click="$set('tab', '{{ $k }}')" class="px-3 py-1.5 rounded-lg text-sm {{ $tab === $k ? 'bg-indigo-600 text-white' : 'bg-white border text-slate-600 hover:text-indigo-600' }}">{{ __($label) }}</button>
            @endforeach
        </div>
        <select wire:model.live="department" class="border rounded-lg px-3 py-2 text-sm bg-white">
            <option value="">{{ __('All departments') }}</option>
            @foreach($departments as $d)<option value="{{ $d->id }}">{{ __($d->name) }}</option>@endforeach
        </select>
    </div>

    @if(session('saved'))<p class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 text-sm">{{ session('saved') }}</p>@endif

    @if($tab === 'daily')
        <div class="bg-white rounded-xl shadow p-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="text-xs text-slate-500">{{ __('Date') }}</label>
                <input wire:model.live="date" type="date" max="{{ today()->toDateString() }}" class="block border rounded-lg p-2 text-sm">
                @error('date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            @can('hr.manage')
                <button wire:click="markAllPresent" class="px-3 py-2 rounded-lg border bg-white text-sm">{{ __('Fill the rest as present (shift times)') }}</button>
            @endcan
            <p class="text-xs text-slate-400 flex-1 min-w-[14rem]">{{ __('Enter the in and out times; late (after the shift start + grace), half day and overtime are worked out. Choose a status only for absence, leave or a holiday.') }}</p>
        </div>

        <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('Employee') }}</th>
                        <th class="px-4 py-3 w-40">{{ __('Status') }}</th>
                        <th class="px-4 py-3 w-32">{{ __('In') }}</th>
                        <th class="px-4 py-3 w-32">{{ __('Out') }}</th>
                        <th class="px-4 py-3">{{ __('Note') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($employees as $e)
                        <tr wire:key="att-{{ $e->id }}">
                            <td class="px-4 py-2"><span class="font-medium">{{ $e->name }}</span> <span class="text-xs text-slate-400">{{ $e->employee_code }}</span></td>
                            <td class="px-4 py-2">
                                <select wire:model="rows.{{ $e->id }}.status" class="w-full border rounded-lg p-1.5 text-sm">
                                    <option value="">—</option>
                                    @foreach(\App\Models\Hr\Attendance::STATUSES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
                                </select>
                            </td>
                            <td class="px-4 py-2"><input wire:model="rows.{{ $e->id }}.in" type="time" class="w-full border rounded-lg p-1.5 text-sm"></td>
                            <td class="px-4 py-2"><input wire:model="rows.{{ $e->id }}.out" type="time" class="w-full border rounded-lg p-1.5 text-sm"></td>
                            <td class="px-4 py-2"><input wire:model="rows.{{ $e->id }}.note" type="text" class="w-full border rounded-lg p-1.5 text-sm"></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No employees whose attendance is tracked.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @can('hr.manage')
            <div class="flex justify-end"><button wire:click="saveDay" class="px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save attendance') }}</button></div>
        @endcan
    @elseif($tab === 'import')
        <div class="bg-white rounded-xl shadow p-5 space-y-4">
            <div class="text-sm text-slate-600 space-y-2">
                <p>{{ __('Two layouts are accepted (a first line with column names is optional):') }}</p>
                <p><b>1.</b> {{ __('One row per person per day') }}: <code class="bg-slate-100 px-1 rounded">EMP-0001,2026-11-20,08:55,18:10</code></p>
                <p><b>2.</b> {{ __('A punch log, e.g. exported from a ZKTeco device — one row per punch; the first punch of the day is the in time, the last the out time') }}: <code class="bg-slate-100 px-1 rounded">15,2026-11-20 08:55:12</code></p>
                <p class="text-xs text-slate-400">{{ __('The first column is the employee code or the attendance device ID set on the employee.') }}</p>
            </div>
            @can('hr.manage')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('CSV file') }}</label>
                        <input wire:model="file" type="file" accept=".csv,.txt,.dat" class="block w-full text-sm mt-1">
                        @error('file') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('…or paste the rows') }}</label>
                        <textarea wire:model="pasted" rows="5" class="w-full border rounded-lg p-2 mt-1 font-mono text-xs"></textarea>
                    </div>
                </div>
                <button wire:click="import" class="px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Import') }}</button>
            @endcan
            @if($importResult)
                <div class="rounded-lg p-3 text-sm {{ $importResult['skipped'] ? 'bg-amber-50 border border-amber-200' : 'bg-green-50 border border-green-200' }}">
                    <p class="font-medium">{{ __(':n day records saved.', ['n' => $importResult['saved']]) }}</p>
                    @foreach(array_slice($importResult['skipped'], 0, 20) as $s)<p class="text-amber-700">{{ $s }}</p>@endforeach
                    @if(count($importResult['skipped']) > 20)<p class="text-amber-700">{{ __('…and :n more.', ['n' => count($importResult['skipped']) - 20]) }}</p>@endif
                </div>
            @endif
        </div>
    @else
        <div class="bg-white rounded-xl shadow p-4 flex items-end gap-3">
            <div>
                <label class="text-xs text-slate-500">{{ __('Month') }}</label>
                <input wire:model.live="month" type="month" class="block border rounded-lg p-2 text-sm">
            </div>
            <p class="text-xs text-slate-400">{{ __('P present · L late · H half day · A absent · LV leave · HO holiday · W weekly off') }}</p>
        </div>
        @php $codes = ['present' => 'P', 'late' => 'L', 'half_day' => 'H', 'absent' => 'A', 'leave' => 'LV', 'holiday' => 'HO', 'weekly_off' => 'W']; @endphp
        <div class="bg-white rounded-xl shadow overflow-x-auto">
            <table class="text-xs">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left sticky left-0 bg-slate-50 min-w-[10rem]">{{ __('Employee') }}</th>
                        @foreach($days as $d)<th class="px-1.5 py-2 {{ $d->isFriday() ? 'text-red-400' : '' }}">{{ $d->day }}</th>@endforeach
                        <th class="px-2 py-2">{{ __('Present') }}</th>
                        <th class="px-2 py-2">{{ __('Absent') }}</th>
                        <th class="px-2 py-2">{{ __('OT h') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($people as $p)
                        <tr>
                            <td class="px-3 py-1.5 sticky left-0 bg-white"><a href="{{ route('hr.employees.show', $p) }}" class="hover:underline">{{ $p->name }}</a></td>
                            @foreach($days as $d)
                                @php $r = $records[$p->id][$d->toDateString()] ?? null; $c = $r ? ($codes[$r->status] ?? '?') : ''; @endphp
                                <td class="px-1.5 py-1.5 text-center {{ in_array($c, ['A']) ? 'text-red-600 font-semibold' : ($c === 'L' ? 'text-amber-600' : 'text-slate-600') }}">{{ $c }}</td>
                            @endforeach
                            @php $s = $summaries[$p->id]; @endphp
                            <td class="px-2 py-1.5 text-center">{{ $s['present'] }}</td>
                            <td class="px-2 py-1.5 text-center {{ $s['absent'] > 0 ? 'text-red-600' : '' }}">{{ $s['absent'] }}</td>
                            <td class="px-2 py-1.5 text-center">{{ round($s['overtime_minutes'] / 60, 1) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="40" class="px-4 py-8 text-center text-slate-400">{{ __('No employees whose attendance is tracked.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
