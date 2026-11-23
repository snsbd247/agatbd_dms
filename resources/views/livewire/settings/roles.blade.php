<div class="space-y-4">
    <x-flash />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500 max-w-2xl">{{ __('Built-in roles are kept up to date by the system and cannot be edited. Make your own role (for example Store Keeper or Cashier), or copy a built-in one and change it, then give it to staff under Users.') }}</p>
        <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('+ New Role') }}</button>
    </div>

    <div class="grid lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow divide-y">
            @foreach($roles as $r)
                @php $builtIn = \App\Support\Roles::isBuiltIn($r->name); @endphp
                <button wire:click="$set('selectedId', {{ $r->id }})" wire:key="role-{{ $r->id }}" class="w-full text-left px-4 py-3 flex items-center justify-between gap-2 {{ $selected?->id === $r->id ? 'bg-indigo-50' : 'hover:bg-slate-50' }}">
                    <span>
                        <span class="font-medium">{{ __($r->name) }}</span>
                        <span class="block text-xs text-slate-400">{{ $builtIn ? __('Built-in') : __('Custom') }} · {{ __(':n permissions', ['n' => $r->permissions->count()]) }} · {{ __(':n users', ['n' => $r->users_count]) }}</span>
                    </span>
                    <x-icon name="chevron-right" class="w-4 h-4 text-slate-300" />
                </button>
            @endforeach
        </div>

        @if($selected)
            @php $builtIn = \App\Support\Roles::isBuiltIn($selected->name); $has = $selected->permissions->pluck('name')->all(); @endphp
            <div class="lg:col-span-2 bg-white rounded-xl shadow p-4">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h2 class="font-semibold">{{ __($selected->name) }}</h2>
                    <div class="flex gap-1">
                        <button wire:click="create({{ $selected->id }})" class="border rounded-lg px-3 py-1.5 text-sm">{{ __('Copy') }}</button>
                        @unless($builtIn)
                            <x-icon-action wire:click="edit({{ $selected->id }})" icon="pencil" color="amber" label="Edit" />
                            <x-icon-action wire:click="delete({{ $selected->id }})" wire:confirm="{{ __('Delete this role?') }}" icon="trash" color="red" label="Delete" />
                        @endunless
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach($groups as $module => $perms)
                        <div class="border rounded-lg p-3">
                            <p class="text-sm font-medium mb-1">{{ __(ucfirst(str_replace('-', ' ', $module))) }}</p>
                            @foreach($perms as $perm => $label)
                                <p class="text-xs flex items-center gap-1.5 {{ in_array($perm, $has, true) ? 'text-green-700' : 'text-slate-300' }}">
                                    <span>{{ in_array($perm, $has, true) ? '✓' : '·' }}</span>{{ __($label) }}
                                </p>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50" wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-3xl p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="font-semibold text-lg mb-4">{{ $editingId ? __('Edit Role') : __('New Role') }}</h3>
                <div>
                    <label class="text-sm text-slate-500">{{ __('Role name') }}</label>
                    <input wire:model="name" type="text" placeholder="{{ __('e.g. Store Keeper') }}" class="w-full border rounded-lg p-2 mt-1">
                    @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-4">
                    @foreach($groups as $module => $perms)
                        <div class="border rounded-lg p-3" wire:key="grp-{{ $module }}">
                            <button type="button" wire:click="toggleGroup('{{ $module }}')" class="text-sm font-medium text-indigo-700 hover:underline mb-1">{{ __(ucfirst(str_replace('-', ' ', $module))) }}</button>
                            @foreach($perms as $perm => $label)
                                <label class="flex items-center gap-2 text-sm py-0.5">
                                    <input type="checkbox" wire:model="permissions" value="{{ $perm }}"> {{ __($label) }}
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
