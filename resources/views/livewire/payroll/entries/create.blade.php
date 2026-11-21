<div>
    <div class="bg-white rounded-xl shadow p-6 max-w-md">
        <p class="text-slate-500 mb-4">{{ __('Generates a payroll entry for every active representative who doesn\'t already have one for the selected period — computing commission (delivered sales minus received returns), bonuses, and outstanding advance deductions automatically.') }}</p>

        <label class="text-sm text-slate-500">{{ __('Period') }}</label>
        <input wire:model="period" type="month" class="w-full border rounded-lg p-2 mt-1">
        @error('period') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('payroll.index') }}" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</a>
            <button wire:click="generate" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Generate') }}</button>
        </div>
    </div>
</div>
