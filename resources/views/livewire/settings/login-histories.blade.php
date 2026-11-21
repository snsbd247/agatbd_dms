<div>
    <div class="grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="database" color="blue" label="Total Logins" :value="$summary['total']" />
        <x-stat-card icon="shield-check" color="green" label="Successful" :value="$summary['success']" />
        <x-stat-card icon="exclamation-triangle" color="red" label="Failed" :value="$summary['failed']" />
        <x-stat-card icon="calendar" color="purple" label="Today" :value="$summary['today']" />
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <select wire:model="filter_status" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
            <option value="">{{ __('All Status') }}</option>
            <option value="success">{{ __('Success') }}</option>
            <option value="failed">{{ __('Failed') }}</option>
        </select>
        <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
        <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">{{ __('User') }}</th>
                    <th class="px-4 py-3">{{ __('IP Address') }}</th>
                    <th class="px-4 py-3">{{ __('Login At') }}</th>
                    <th class="px-4 py-3">{{ __('Logout At') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($histories as $index => $history)
                    <tr wire:key="lh-{{ $history->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $histories->firstItem() + $index }}</td>
                        <td class="px-4 py-3 font-medium" data-label="{{ __('User') }}">{{ $history->user?->name ?? 'Unknown' }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('IP Address') }}">{{ $history->ip_address }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Login At') }}">{{ $history->login_at?->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Logout At') }}">{{ $history->logout_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}">
                            <span class="px-2 py-1 rounded-full text-xs {{ $history->status === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ __(ucfirst($history->status)) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No login history yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$histories" />
</div>
