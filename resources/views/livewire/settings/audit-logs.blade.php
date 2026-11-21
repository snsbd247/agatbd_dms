@php
    $fmt = function ($v) {
        if (is_null($v)) return '—';
        if (is_bool($v)) return $v ? 'yes' : 'no';
        if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
        return \Illuminate\Support\Str::limit((string) $v, 80);
    };
    $label = fn ($a) => ucwords(str_replace(['_', '.'], [' ', ' · '], $a));
    $color = fn ($a) => match (true) {
        str_ends_with($a, '.created') => 'bg-green-100 text-green-700',
        str_ends_with($a, '.deleted') => 'bg-red-100 text-red-700',
        str_ends_with($a, '.updated') => 'bg-amber-100 text-amber-700',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp

<div>
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="database" color="blue" label="Total Entries" :value="number_format($summary['total'])" />
        <x-stat-card icon="calendar" color="green" label="Today" :value="number_format($summary['today'])" />
        <x-stat-card icon="pencil" color="orange" label="Updates" :value="number_format($summary['updated'])" />
        <x-stat-card icon="trash" color="red" label="Deletions" :value="number_format($summary['deleted'])" />
    </div>

    <div class="bg-white rounded-xl shadow p-4 mb-4 grid grid-cols-2 lg:grid-cols-6 gap-2">
        <input wire:model.live.debounce.400ms="search" type="search" placeholder="{{ __('Search (number, name, value)...') }}" class="border rounded-lg px-3 py-2 text-sm col-span-2">
        <select wire:model.live="module" class="border rounded-lg px-3 py-2 text-sm bg-white">
            <option value="">{{ __('All modules') }}</option>
            @foreach($modules as $m)
                <option value="{{ $m }}">{{ __(ucwords(str_replace('_', ' ', $m))) }}</option>
            @endforeach
        </select>
        <select wire:model.live="event" class="border rounded-lg px-3 py-2 text-sm bg-white">
            <option value="">{{ __('All actions') }}</option>
            <option value="created">{{ __('Created') }}</option>
            <option value="updated">{{ __('Updated') }}</option>
            <option value="deleted">{{ __('Deleted') }}</option>
        </select>
        <select wire:model.live="userId" class="border rounded-lg px-3 py-2 text-sm bg-white">
            <option value="">{{ __('All users') }}</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
            <option value="system">{{ __('System') }}</option>
        </select>
        <div class="flex gap-2 col-span-2 lg:col-span-1">
            <input wire:model.live="from" type="date" class="border rounded-lg px-2 py-2 text-sm w-full" title="{{ __('From') }}">
            <input wire:model.live="to" type="date" class="border rounded-lg px-2 py-2 text-sm w-full" title="{{ __('To') }}">
        </div>
        <button wire:click="resetFilters" class="lg:col-start-6 border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white col-span-2 lg:col-span-1">{{ __('Reset') }}</button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('When') }}</th>
                    <th class="px-4 py-3">{{ __('User') }}</th>
                    <th class="px-4 py-3">{{ __('Action') }}</th>
                    <th class="px-4 py-3">{{ __('Record') }}</th>
                    <th class="px-4 py-3">{{ __('Changes') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($logs as $log)
                    @php
                        $new = (array) ($log->new_values ?? []);
                        $old = (array) ($log->old_values ?? []);
                        $isUpdate = str_ends_with($log->action, '.updated') || ($old && $new);
                        $fields = $isUpdate ? array_keys($new) : array_keys($new ?: $old);
                        $ref = $new['number'] ?? $old['number'] ?? $new['name'] ?? $old['name'] ?? ($log->auditable_id ? '#'.$log->auditable_id : '');
                        $open = $expanded === $log->id;
                    @endphp
                    <tr wire:key="log-{{ $log->id }}" class="align-top">
                        <td class="px-4 py-3 text-slate-500 whitespace-nowrap" data-label="When">{{ $log->created_at->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3 font-medium" data-label="User">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="px-4 py-3" data-label="Action"><span class="px-2 py-1 rounded-full text-xs whitespace-nowrap {{ $color($log->action) }}">{{ $label($log->action) }}</span></td>
                        <td class="px-4 py-3 text-slate-600" data-label="Record">{{ $ref }}</td>
                        <td class="px-4 py-3 text-xs rtable-block" data-label="Changes">
                            @if($isUpdate)
                                <div class="space-y-0.5">
                                    @foreach(array_slice($fields, 0, $open ? 100 : 4) as $f)
                                        <p><span class="text-slate-500">{{ $f }}:</span> <span class="text-red-600 line-through">{{ $fmt($old[$f] ?? null) }}</span> → <span class="text-green-700">{{ $fmt($new[$f] ?? null) }}</span></p>
                                    @endforeach
                                </div>
                            @elseif($fields)
                                <div class="space-y-0.5 {{ str_ends_with($log->action, '.deleted') ? 'text-red-700' : 'text-slate-600' }}">
                                    @foreach(array_slice($fields, 0, $open ? 100 : 4) as $f)
                                        <p><span class="text-slate-500">{{ $f }}:</span> {{ $fmt(($new ?: $old)[$f] ?? null) }}</p>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                            @if(count($fields) > 4)
                                <button wire:click="toggle({{ $log->id }})" class="text-indigo-600 hover:underline mt-1">{{ $open ? 'Show less' : 'Show all '.count($fields).' fields' }}</button>
                            @endif
                            @if($log->ip_address)
                                <p class="text-slate-400 mt-1">IP {{ $log->ip_address }}</p>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No audit log entries match.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$logs" />
</div>
