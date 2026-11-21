<div class="max-w-md">
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-semibold mb-4">{{ __('Change Password') }}</h2>

        @if($saved)
            <div class="bg-green-50 text-green-700 text-sm rounded-lg px-4 py-2 mb-4">{{ __('Password updated successfully.') }}</div>
        @endif

        <div class="space-y-4">
            <div>
                <label class="text-sm text-slate-500">{{ __('Current Password') }}</label>
                <input wire:model="current_password" type="password" class="w-full border rounded-lg p-2 mt-1">
                @error('current_password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm text-slate-500">{{ __('New Password') }}</label>
                <input wire:model="password" type="password" class="w-full border rounded-lg p-2 mt-1">
                @error('password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm text-slate-500">{{ __('Confirm New Password') }}</label>
                <input wire:model="password_confirmation" type="password" class="w-full border rounded-lg p-2 mt-1">
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Update Password') }}</button>
        </div>
    </div>
</div>
