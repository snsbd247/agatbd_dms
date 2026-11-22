<div>
    <div class="grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="user-group" color="blue" label="Total Users" :value="$summary['total']" />
        <x-stat-card icon="shield-check" color="green" label="Admins" :value="$summary['dealers']" />
        <x-stat-card icon="user" color="purple" label="Managers" :value="$summary['managers']" />
        <x-stat-card icon="database" color="orange" label="New This Month" :value="$summary['new_this_month']" />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model="filter_role" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
                <option value="">{{ __('All Roles') }}</option>
                <option value="Admin">{{ __('Admin') }}</option><option value="Accountant">{{ __('Accountant') }}</option><option value="HR Manager">{{ __('HR Manager') }}</option><option value="Factory Manager">{{ __('Factory Manager') }}</option>
                <option value="Manager">{{ __('Manager') }}</option>
            </select>
            <input wire:model="search" type="text" placeholder="{{ __('Search by name or email...') }}" class="border rounded-lg px-3 py-2 text-sm w-full sm:w-auto sm:min-w-[200px]">
            <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
            <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
        </div>
        @error('delete') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ Add User') }}</button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Email') }}</th>
                    <th class="px-4 py-3">{{ __('Role') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($users as $index => $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $users->firstItem() + $index }}</td>
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Name') }}">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Email') }}">{{ $user->email }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Role') }}">
                            <span class="px-2 py-1 rounded-full text-xs bg-indigo-100 text-indigo-700">{{ $user->getRoleNames()->first() }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <x-icon-action wire:click="edit({{ $user->id }})" icon="pencil" color="amber" label="Edit" />
                                <x-icon-action wire:click="delete({{ $user->id }})" wire:confirm="{{ __('Delete this user?') }}" icon="trash" color="red" label="Delete" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No staff users found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$users" />

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-md shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? 'Edit' : 'Add' }} User</h2>

                <div class="space-y-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Name') }}</label>
                        <input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Email') }}</label>
                        <input wire:model="email" type="email" class="w-full border rounded-lg p-2 mt-1">
                        @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Password :v', ['v' => $editingId ? __('(leave blank to keep current)') : '']) }}</label>
                        <input wire:model="password" type="password" class="w-full border rounded-lg p-2 mt-1">
                        @error('password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Role') }}</label>
                        <select wire:model="form_role" class="w-full border rounded-lg p-2 mt-1">
                            <option value="Admin">{{ __('Admin') }}</option><option value="Accountant">{{ __('Accountant') }}</option><option value="HR Manager">{{ __('HR Manager') }}</option><option value="Factory Manager">{{ __('Factory Manager') }}</option>
                            <option value="Manager">{{ __('Manager') }}</option>
                        </select>
                        @error('form_role') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
