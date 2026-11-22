<div class="space-y-4">
    <div class="flex justify-between items-start gap-3">
        <p class="text-sm text-slate-500">{{ __('ZKTeco (or any ADMS) attendance machines send each punch to this site by themselves. The first punch of a day is the in-time, the last is the out-time. Days corrected by hand are not overwritten.') }}</p>
        @can('hr.manage')<button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ Add') }}</button>@endcan
    </div>

    <details class="bg-white rounded-xl shadow p-4 text-sm">
        <summary class="font-semibold text-slate-700 cursor-pointer">{{ __('How to connect a machine') }}</summary>
        <ol class="list-decimal ml-5 mt-3 space-y-1 text-slate-600">
            <li>{{ __('On the machine: Menu → Comm. → Cloud Server Setting (ADMS).') }}</li>
            <li>{{ __('Server address') }}: <code class="bg-slate-100 px-1 rounded">{{ $serverHost }}</code>, {{ __('port') }} <code class="bg-slate-100 px-1 rounded">443</code>, {{ __('HTTPS on') }}. {{ __('Proxy off. The machine needs internet (LAN cable or Wi-Fi).') }}</li>
            <li>{{ __('Within a minute the machine appears below as a new, inactive machine. Check its serial number (Menu → System Info → Device Info) and switch it on.') }}</li>
            <li>{{ __('In HR → Employees, put each person\'s machine user ID in "Attendance device ID". Punches from an ID no one has yet wait here; press "Process waiting punches" after adding IDs.') }}</li>
            <li>{{ __('The machine\'s clock should be right (Bangladesh time); it sends the time it shows.') }}</li>
        </ol>
    </details>

    @if($message)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ $message }}</div>
    @endif

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Serial number') }}</th>
                    <th class="px-4 py-3">{{ __('Location') }}</th>
                    <th class="px-4 py-3">{{ __('Last contact') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Punches') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($devices as $d)
                    <tr wire:key="dev-{{ $d->id }}" class="{{ $d->active ? '' : 'bg-amber-50/60' }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Name') }}">
                            {{ $d->name }}
                            @unless($d->active)<span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">{{ __('Inactive') }}</span>@endunless
                        </td>
                        <td class="px-4 py-3 font-mono text-xs" data-label="{{ __('Serial number') }}">{{ $d->serial_no }}</td>
                        <td class="px-4 py-3 text-slate-600" data-label="{{ __('Location') }}">{{ $d->location ?: '—' }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Last contact') }}">
                            @if($d->last_seen_at)
                                <span class="inline-block h-2 w-2 rounded-full {{ $d->online() ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                {{ $d->last_seen_at->diffForHumans() }}
                                <span class="text-xs text-slate-400">{{ $d->last_ip }}</span>
                            @else
                                <span class="text-slate-400">{{ __('Never') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Punches') }}">{{ $d->punch_count }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @can('hr.manage')
                                <button wire:click="toggle({{ $d->id }})" class="text-xs font-medium {{ $d->active ? 'text-slate-500' : 'text-emerald-700' }} hover:underline mr-2">{{ $d->active ? __('Switch off') : __('Switch on') }}</button>
                                <x-icon-action wire:click="edit({{ $d->id }})" icon="pencil" color="amber" label="Edit" />
                                <x-icon-action wire:click="delete({{ $d->id }})" wire:confirm="{{ __('Remove this machine? Its punches are kept.') }}" icon="trash" color="red" label="Delete" />
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No machines yet. Point a machine at this site and it will appear here.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
            <h3 class="font-semibold text-slate-700">{{ __('Recent punches') }}</h3>
            @if($pending > 0)
                <div class="flex items-center gap-3 text-sm">
                    <span class="text-amber-700">{{ __(':n punches waiting for an employee', ['n' => $pending]) }} ({{ $pendingIds->implode(', ') }})</span>
                    @can('hr.manage')<button wire:click="processPending" class="rounded-lg border px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Process waiting punches') }}</button>@endcan
                </div>
            @endif
        </div>
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Time') }}</th>
                    <th class="px-4 py-3">{{ __('Machine user ID') }}</th>
                    <th class="px-4 py-3">{{ __('Employee') }}</th>
                    <th class="px-4 py-3">{{ __('Machine') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($recent as $p)
                    <tr>
                        <td class="px-4 py-2" data-label="{{ __('Time') }}">{{ $p->punched_at->format('d M Y h:i A') }}</td>
                        <td class="px-4 py-2 font-mono text-xs" data-label="{{ __('Machine user ID') }}">{{ $p->device_user_id }}</td>
                        <td class="px-4 py-2" data-label="{{ __('Employee') }}">{!! $p->employee ? e($p->employee->name) : '<span class="text-amber-700">'.e(__('Not matched')).'</span>' !!}</td>
                        <td class="px-4 py-2 text-slate-500" data-label="{{ __('Machine') }}">{{ $p->device?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">{{ __('No punches received yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-lg shadow-2xl">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('Edit') : __('New attendance machine') }}</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Name') }}</label>
                        <input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1" placeholder="{{ __('e.g. Factory gate') }}">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Serial number') }}</label>
                        <input wire:model="serial_no" type="text" class="w-full border rounded-lg p-2 mt-1 font-mono">
                        @error('serial_no') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Location') }}</label>
                        <input wire:model="location" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input wire:model="active" type="checkbox" class="rounded"> {{ __('Active') }}</label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
