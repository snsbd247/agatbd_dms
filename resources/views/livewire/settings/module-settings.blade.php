<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-slate-800">{{ __('Business Type & Modules') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Choose what kind of business this is to load its modules, then switch off what you do not need. Switching a module off hides it everywhere for everyone; its data is kept and comes back when you switch it on again.') }}</p>
    </div>

    @if($message)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ $message }}</div>
    @endif

    {{-- business type --}}
    <section class="bg-white rounded-xl shadow p-5">
        <h3 class="font-semibold text-slate-700 mb-3">{{ __('Business type') }}</h3>
        @php
            $typeInfo = [
                'trading' => ['shopping-cart', 'Buys from manufacturers or imports, fixes a price from cost and overheads, and sells itself or through dealers.'],
                'manufacturing' => ['factory', 'Buys or imports raw materials, sets up formulas and overheads to fix a price, produces, and sells itself or through dealers.'],
                'both' => ['grid', 'Produces its own products and also trades bought or imported goods, kits and repacked items.'],
            ];
        @endphp
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            @foreach(\App\Support\Modules::TYPES as $key => $label)
                @php $current = $type === $key; $picked = $pendingType === $key; @endphp
                <button type="button" wire:click="pickType('{{ $key }}')" class="text-left rounded-xl border-2 p-4 transition {{ $current ? 'border-indigo-600 bg-indigo-50' : ($picked ? 'border-amber-500 bg-amber-50' : 'border-slate-200 hover:border-slate-300') }}">
                    <span class="flex items-center gap-2 font-semibold text-slate-800">
                        <x-icon :name="$typeInfo[$key][0]" class="w-5 h-5 {{ $current ? 'text-indigo-600' : 'text-slate-400' }}" />
                        {{ __($label) }}
                        @if($current)<span class="ml-auto rounded bg-indigo-600 px-2 py-0.5 text-xs text-white">{{ __('Current') }}</span>@endif
                    </span>
                    <span class="mt-2 block text-sm text-slate-500">{{ __($typeInfo[$key][1]) }}</span>
                </button>
            @endforeach
        </div>
        @if(! $type)
            <p class="mt-3 text-sm text-amber-700">{{ __('No type chosen yet, so every module is on. Pick one to load its template.') }}</p>
        @endif

        @if($pendingDiff !== null)
            <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">
                <p class="font-medium text-amber-900">{{ __('Load the ":t" template?', ['t' => __(\App\Support\Modules::TYPES[$pendingType])]) }}</p>
                @if($pendingDiff['on'])<p class="mt-2 text-emerald-800">{{ __('Will switch on') }}: {{ implode(', ', $pendingDiff['on']) }}</p>@endif
                @if($pendingDiff['off'])<p class="mt-1 text-rose-700">{{ __('Will switch off') }}: {{ implode(', ', $pendingDiff['off']) }}</p>@endif
                @if(! $pendingDiff['on'] && ! $pendingDiff['off'])<p class="mt-2 text-slate-600">{{ __('No module changes.') }}</p>@endif
                <p class="mt-2 text-xs text-slate-500">{{ __('No data is deleted.') }}</p>
                <div class="mt-3 flex gap-2">
                    <button wire:click="applyType" class="rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-500">{{ __('Load template') }}</button>
                    <button wire:click="$set('pendingType', null)" class="rounded-lg px-4 py-2 text-slate-600">{{ __('Cancel') }}</button>
                </div>
            </div>
        @endif
    </section>

    {{-- modules --}}
    @foreach(\App\Support\Modules::GROUPS as $groupKey => $groupLabel)
        @continue(! isset($groups[$groupKey]))
        <section>
            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __($groupLabel) }}</h3>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($groups[$groupKey] as $m)
                    <div wire:key="mod-{{ $m['key'] }}" class="rounded-xl border bg-white p-4 shadow-sm {{ $m['coming'] ? 'border-dashed opacity-70' : '' }} {{ ! $m['enabled'] && ! $m['coming'] ? 'bg-slate-50' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold {{ $m['enabled'] || $m['core'] ? 'text-slate-800' : 'text-slate-500' }}">{{ __($m['label']) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ __($m['description']) }}</p>
                            </div>
                            @if($m['core'])
                                <span class="shrink-0 flex items-center gap-1 rounded bg-slate-100 px-2 py-1 text-xs text-slate-500"><x-icon name="lock-closed" class="w-3.5 h-3.5" /> {{ __('Always on') }}</span>
                            @elseif($m['coming'])
                                <span class="shrink-0 rounded bg-sky-50 px-2 py-1 text-xs text-sky-700">{{ __('Coming (phase :p)', ['p' => $m['coming']]) }}</span>
                            @else
                                <button type="button" wire:click="toggle('{{ $m['key'] }}')"
                                    @if($m['enabled'] && ($m['dependents'] || isset($openWork[$m['key']])))
                                        wire:confirm="{{ collect([isset($openWork[$m['key']]) ? $openWork[$m['key']].'.' : null, $m['dependents'] ? __('This also switches off: :list.', ['list' => implode(', ', $m['dependents'])]) : null, __('Switch it off?')])->filter()->implode(' ') }}"
                                    @endif
                                    role="switch" aria-checked="{{ $m['enabled'] ? 'true' : 'false' }}"
                                    class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition {{ $m['enabled'] ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                    <span class="sr-only">{{ __($m['label']) }}</span>
                                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition {{ $m['enabled'] ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
                                </button>
                            @endif
                        </div>
                        @if(! $m['core'] && ! $m['coming'])
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                @if($type && ! $m['inTemplate'])<span class="rounded bg-slate-100 px-2 py-0.5 text-slate-500">{{ __('Not in the template') }}</span>@endif
                                @if($m['requires'])<span class="rounded bg-slate-100 px-2 py-0.5 text-slate-500">{{ __('Needs') }}: {{ collect($m['requires'])->map(fn ($r) => __(\App\Support\Modules::all()[$r]['label']))->implode(', ') }}</span>@endif
                                @if($m['enabled'] && isset($openWork[$m['key']]))<span class="rounded bg-amber-50 px-2 py-0.5 text-amber-700">{{ $openWork[$m['key']] }}</span>@endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
