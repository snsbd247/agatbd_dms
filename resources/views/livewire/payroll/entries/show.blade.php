<div class="max-w-2xl">
    <a href="{{ route('payroll.index') }}" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" /> {{ __('Back to Payroll') }}
    </a>

    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex justify-between items-start gap-4">
            <div class="flex items-start gap-4 min-w-0">
                <div class="w-12 h-12 rounded-xl bg-purple-600 text-white flex items-center justify-center shrink-0">
                    <x-icon name="wallet" class="w-6 h-6" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold break-words">{{ $payrollEntry->number }}</h2>
                    <p class="text-slate-500 mt-1">{{ $payrollEntry->representative?->name }} &middot; {{ $payrollEntry->period }}</p>
                </div>
            </div>
            <div class="text-right shrink-0">
                <span class="px-3 py-1 rounded-full text-sm {{ $payrollEntry->status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                    {{ __(ucfirst($payrollEntry->status)) }}
                </span>
                <p class="text-2xl font-bold mt-2">৳ {{ number_format($payrollEntry->net_salary, 2) }}</p>
                @if($payrollEntry->paid_at)
                    <p class="text-xs text-slate-400 mt-1">{{ __('Paid :v', ['v' => $payrollEntry->paid_at->format('d M Y H:i')]) }}</p>
                @endif
            </div>
        </div>

        @can('payroll.manage')
            @if($payrollEntry->status === 'pending')
                <div class="mt-4">
                    <button wire:click="markPaid" wire:confirm="{{ __('Mark this payroll entry as paid?') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg font-medium">{{ __('Mark as Paid') }}</button>
                </div>
            @endif
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-semibold mb-4 flex items-center gap-2"><x-icon name="database" class="w-5 h-5 text-slate-400" /> {{ __('Breakdown') }}</h2>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-slate-500">{{ __('Base Salary') }}</span>
                <span>৳ {{ number_format($payrollEntry->base_salary, 2) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">
                    Commission
                    @if($commission)
                        <span class="text-xs">(৳ {{ number_format($commission->delivered_amount, 2) }} delivered − ৳ {{ number_format($commission->return_adjustment, 2) }} returned, at {{ $commission->commission_rate }}%)</span>
                    @endif
                </span>
                <span class="text-green-700">+ ৳ {{ number_format($payrollEntry->commission_amount, 2) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">{{ __('Bonus') }}</span>
                <span class="text-green-700">+ ৳ {{ number_format($payrollEntry->bonus_amount, 2) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">{{ __('Advance Deduction') }}</span>
                <span class="text-red-600">− ৳ {{ number_format($payrollEntry->advance_deduction, 2) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">{{ __('Other Deduction') }}</span>
                <span class="text-red-600">− ৳ {{ number_format($payrollEntry->other_deduction, 2) }}</span>
            </div>
            <div class="flex justify-between pt-2 border-t font-semibold">
                <span>{{ __('Net Salary') }}</span>
                <span>৳ {{ number_format($payrollEntry->net_salary, 2) }}</span>
            </div>
        </div>
    </div>
</div>
