<div class="space-y-6">
    <div class="bg-white rounded-xl shadow p-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs text-slate-400">{{ $dealer->code }}</p>
            <h2 class="text-xl font-bold">{{ $dealer->name }}</h2>
            <p class="text-sm text-slate-500">{{ collect([$dealer->owner_name, $dealer->phone, $dealer->email, $dealer->area?->name])->filter()->implode(' · ') }}</p>
            @if($dealer->address)<p class="text-sm text-slate-500">{{ $dealer->address }}</p>@endif
        </div>
        <div class="flex items-center gap-2">
            <span class="px-2 py-1 rounded-full text-xs {{ $dealer->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $dealer->active ? __('Active') : __('Inactive') }}</span>
            <a href="{{ route('dealers.index') }}" class="text-sm text-slate-500 hover:text-indigo-600">{{ __('← All dealers') }}</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow p-5">
            <h3 class="font-semibold mb-1">{{ __('Account with Agro Tech') }}</h3>
            <p class="text-xs text-slate-400 mb-4">{{ __('What the dealer buys from the head office (primary sales).') }}</p>
            @php $due = (float) ($account?->current_due ?? 0); $limit = (float) ($account?->credit_limit ?? 0); @endphp
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-lg bg-slate-50 p-3"><p class="text-slate-500">{{ __('Due') }}</p><p class="text-lg font-semibold {{ $limit > 0 && $due > $limit ? 'text-red-600' : '' }}">৳ {{ number_format($due, 2) }}</p></div>
                <div class="rounded-lg bg-slate-50 p-3"><p class="text-slate-500">{{ __('Credit Limit') }}</p><p class="text-lg font-semibold">৳ {{ number_format($limit, 2) }}</p></div>
                <div class="rounded-lg bg-slate-50 p-3"><p class="text-slate-500">{{ __('Bought this month') }}</p><p class="font-semibold">৳ {{ number_format($primary['month'], 2) }}</p></div>
                <div class="rounded-lg bg-slate-50 p-3"><p class="text-slate-500">{{ __('Paid this month') }}</p><p class="font-semibold">৳ {{ number_format($primary['paid_month'], 2) }}</p></div>
            </div>
            <p class="text-sm text-slate-500 mt-3">{{ __('Last 12 months') }}: <span class="font-medium text-slate-700">৳ {{ number_format($primary['year'], 2) }}</span></p>
            @if($account)
                <div class="flex flex-wrap gap-3 mt-4 text-sm">
                    <a href="{{ route('retailers.show', $account) }}" class="text-indigo-600 hover:underline">{{ __('Account details & ledger') }}</a>
                    @can('sales.create')<a href="{{ route('sales.orders.create', ['retailer' => $account->id]) }}" class="text-indigo-600 hover:underline">{{ __('New order for this dealer') }}</a>@endcan
                </div>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow p-5">
            <h3 class="font-semibold mb-1">{{ __('Dealer\'s own sales') }}</h3>
            <p class="text-xs text-slate-400 mb-4">{{ __('What the dealer sells to its customers (secondary sales).') }}</p>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-lg bg-slate-50 p-3"><p class="text-slate-500">{{ __('Sold this month') }}</p><p class="text-lg font-semibold">৳ {{ number_format($secondary['month'], 2) }}</p></div>
                <div class="rounded-lg bg-slate-50 p-3"><p class="text-slate-500">{{ __('Last 12 months') }}</p><p class="text-lg font-semibold">৳ {{ number_format($secondary['year'], 2) }}</p></div>
                <div class="rounded-lg bg-slate-50 p-3"><p class="text-slate-500">{{ __('Customers') }}</p><p class="font-semibold">{{ $customers }}</p></div>
                <div class="rounded-lg bg-slate-50 p-3"><p class="text-slate-500">{{ __('Customers owe the dealer') }}</p><p class="font-semibold">৳ {{ number_format($secondary['due'], 2) }}</p></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-5">
        <h3 class="font-semibold mb-3">{{ __('Warehouses & stock') }}</h3>
        <div class="divide-y">
            @forelse($warehouses as $wh)
                <div class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                    <span class="font-medium">{{ $wh->name }} <span class="text-xs text-slate-400">{{ $wh->purposeLabel() }}{{ $wh->active ? '' : ' · '.__('Inactive') }}</span></span>
                    <span class="text-slate-600">{{ rtrim(rtrim(number_format($wh->stock_units, 3), '0'), '.') ?: '0' }} {{ __('units') }} · ৳ {{ number_format($wh->stock_value, 2) }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">{{ __('No warehouse yet. Goods from Agro Tech need one to arrive in.') }}</p>
            @endforelse
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow p-5">
            <h3 class="font-semibold mb-3">{{ __('Marketing staff') }}</h3>

            <p class="text-xs font-semibold text-slate-500 uppercase mb-1">{{ __('Dealer\'s own staff') }}</p>
            @forelse($dealer->ownRepresentatives as $rep)
                <p class="text-sm py-1">{{ $rep->name }} <span class="text-xs text-slate-400">{{ $rep->employee_code }}</span></p>
            @empty
                <p class="text-sm text-slate-400 mb-1">{{ __('None. The dealer adds them from its own panel.') }}</p>
            @endforelse

            <p class="text-xs font-semibold text-slate-500 uppercase mt-4 mb-1">{{ __('Agro Tech staff placed here') }}</p>
            @forelse($dealer->placedRepresentatives as $rep)
                <div class="flex items-center justify-between text-sm py-1">
                    <span>{{ $rep->name }} <span class="text-xs text-slate-400">{{ $rep->employee_code }}</span></span>
                    @can('dealers.manage')
                        <button wire:click="removeRepresentative({{ $rep->id }})" wire:confirm="{{ __('Remove this person from the dealer?') }}" class="text-xs text-red-600 hover:underline">{{ __('Remove') }}</button>
                    @endcan
                </div>
            @empty
                <p class="text-sm text-slate-400">{{ __('None yet.') }}</p>
            @endforelse

            @can('dealers.manage')
                <div class="flex gap-2 mt-3">
                    <select wire:model="place_representative_id" class="border rounded-lg p-2 text-sm flex-1">
                        <option value="">{{ __('Place an Agro Tech representative…') }}</option>
                        @foreach($availableReps as $rep)<option value="{{ $rep->id }}">{{ $rep->name }} ({{ $rep->employee_code }})</option>@endforeach
                    </select>
                    <button wire:click="placeRepresentative" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-3 py-2 text-sm font-medium">{{ __('Add') }}</button>
                </div>
                @error('place_representative_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            @endcan
        </div>

        <div class="bg-white rounded-xl shadow p-5">
            <h3 class="font-semibold mb-3">{{ __('Dealer logins') }}</h3>
            @forelse($dealer->users as $user)
                <p class="text-sm py-1">{{ $user->name }} <span class="text-xs text-slate-400">{{ $user->email }}</span></p>
            @empty
                <p class="text-sm text-slate-400">{{ __('No login yet.') }}</p>
            @endforelse

            @can('dealers.manage')
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-3">
                    <div>
                        <input wire:model="login_name" type="text" placeholder="{{ __('Name') }}" class="border rounded-lg p-2 text-sm w-full">
                        @error('login_name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input wire:model="login_email" type="email" autocomplete="off" placeholder="{{ __('Email') }}" class="border rounded-lg p-2 text-sm w-full">
                        @error('login_email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input wire:model="login_password" type="password" autocomplete="new-password" placeholder="{{ __('Password') }}" class="border rounded-lg p-2 text-sm w-full">
                        @error('login_password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <button wire:click="addLogin" class="mt-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-3 py-2 text-sm font-medium">{{ __('Add login') }}</button>
            @endcan
        </div>
    </div>
</div>
