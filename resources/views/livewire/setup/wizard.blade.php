<div class="mx-auto max-w-3xl space-y-5">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">{{ __('Welcome! Let\'s set up the system') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('Four short steps. Everything can be changed later in Settings.') }}</p>
    </div>

    <ol class="grid grid-cols-4 gap-2 text-xs">
        @foreach([1 => 'Business type', 2 => 'Company', 3 => 'Year & VAT', 4 => 'Warehouses'] as $k => $l)
            <li class="rounded-lg px-3 py-2 text-center {{ $step === $k ? 'bg-indigo-600 text-white' : ($step > $k ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500') }}">{{ $k }}. {{ __($l) }}</li>
        @endforeach
    </ol>

    <section class="rounded-xl bg-white p-6 shadow space-y-4">
        @if($step === 1)
            <h3 class="font-semibold text-slate-700">{{ __('What kind of business is this?') }}</h3>
            @php
                $info = [
                    'trading' => ['shopping-cart', 'Buys from manufacturers or imports, fixes a price from cost and overheads, and sells itself or through dealers.'],
                    'manufacturing' => ['factory', 'Buys or imports raw materials, sets up formulas and overheads to fix a price, produces, and sells itself or through dealers.'],
                    'both' => ['grid', 'Produces its own products and also trades bought or imported goods, kits and repacked items.'],
                ];
            @endphp
            <div class="grid gap-3 md:grid-cols-3">
                @foreach(\App\Support\Modules::TYPES as $k => $l)
                    <button type="button" wire:click="$set('type', '{{ $k }}')" class="rounded-xl border-2 p-4 text-left {{ $type === $k ? 'border-indigo-600 bg-indigo-50' : 'border-slate-200 hover:border-slate-300' }}">
                        <span class="flex items-center gap-2 font-semibold text-slate-800"><x-icon :name="$info[$k][0]" class="w-5 h-5 text-indigo-600" /> {{ __($l) }}</span>
                        <span class="mt-2 block text-sm text-slate-500">{{ __($info[$k][1]) }}</span>
                    </button>
                @endforeach
            </div>
            @error('type')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        @elseif($step === 2)
            <h3 class="font-semibold text-slate-700">{{ __('Company details (printed on invoices)') }}</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="text-sm text-slate-500">{{ __('Company name') }}</label><input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1">@error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2"><label class="text-sm text-slate-500">{{ __('Address') }}</label><input wire:model="address" type="text" class="w-full border rounded-lg p-2 mt-1"></div>
                <div><label class="text-sm text-slate-500">{{ __('Phone') }}</label><input wire:model="phone" type="text" class="w-full border rounded-lg p-2 mt-1"></div>
                <div><label class="text-sm text-slate-500">{{ __('Email') }}</label><input wire:model="email" type="email" class="w-full border rounded-lg p-2 mt-1">@error('email')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror</div>
            </div>
            <p class="text-xs text-slate-400">{{ __('The logo can be added in Settings → Company Profile.') }}</p>
        @elseif($step === 3)
            <h3 class="font-semibold text-slate-700">{{ __('Fiscal year and VAT') }}</h3>
            <div>
                <label class="text-sm text-slate-500">{{ __('Fiscal year starts in') }}</label>
                <select wire:model="fiscal_start" class="w-full sm:w-64 border rounded-lg p-2 mt-1">
                    @foreach(range(1, 12) as $m)<option value="{{ $m }}">{{ \Illuminate\Support\Carbon::create(2000, $m, 1)->locale(app()->getLocale())->translatedFormat('F') }}</option>@endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input wire:model.live="vat" type="checkbox" class="rounded"> {{ __('We are VAT registered (show VAT, print Mushak 6.3 / 4.3)') }}</label>
            @if($vat)
                <div><label class="text-sm text-slate-500">{{ __('BIN (VAT registration number)') }}</label><input wire:model="bin" type="text" maxlength="20" class="w-full sm:w-64 border rounded-lg p-2 mt-1">@error('bin')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror</div>
            @endif
        @else
            <h3 class="font-semibold text-slate-700">{{ __('Warehouses') }}</h3>
            <div><label class="text-sm text-slate-500">{{ __('Main warehouse / depot name') }}</label><input wire:model="warehouse" type="text" class="w-full border rounded-lg p-2 mt-1">@error('warehouse')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror</div>
            @if($type !== 'trading')
                <div><label class="text-sm text-slate-500">{{ __('Factory name') }}</label><input wire:model="factory" type="text" class="w-full border rounded-lg p-2 mt-1">@error('factory')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror</div>
                <p class="text-xs text-slate-500">{{ __('The factory gets a raw material store, a finished goods store and a damage store.') }}</p>
            @endif
            <p class="text-xs text-slate-500">{{ __('A damage store for the head office is made too. More warehouses can be added in Inventory → Warehouses.') }}</p>
        @endif
    </section>

    <div class="flex items-center justify-between">
        <button wire:click="skip" wire:confirm="{{ __('Skip setup? Every module stays on; you can set things up in Settings.') }}" class="text-sm text-slate-500 hover:underline">{{ __('Skip setup') }}</button>
        <div class="flex gap-2">
            @if($step > 1)<button wire:click="back" class="rounded-lg border bg-white px-4 py-2 text-slate-600">{{ __('Back') }}</button>@endif
            @if($step < 4)
                <button wire:click="next" class="rounded-lg bg-indigo-600 px-5 py-2 font-medium text-white hover:bg-indigo-500">{{ __('Next') }}</button>
            @else
                <button wire:click="finish" class="rounded-lg bg-emerald-600 px-5 py-2 font-medium text-white hover:bg-emerald-500">{{ __('Finish') }}</button>
            @endif
        </div>
    </div>
</div>
