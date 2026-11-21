<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-semibold mb-4">Company Profile</h2>

        @if($saved)
            <div class="bg-green-50 text-green-700 text-sm rounded-lg px-4 py-2 mb-4">Saved successfully.</div>
        @endif

        <div class="space-y-4">
            <div>
                <label class="text-sm text-slate-500">Company Name</label>
                <input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1">
                @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm text-slate-500">Address</label>
                <input wire:model="address" type="text" class="w-full border rounded-lg p-2 mt-1">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-slate-500">Phone</label>
                    <input wire:model="phone" type="text" class="w-full border rounded-lg p-2 mt-1">
                </div>
                <div>
                    <label class="text-sm text-slate-500">Email</label>
                    <input wire:model="email" type="email" class="w-full border rounded-lg p-2 mt-1">
                    @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="text-sm text-slate-500">Invoice Footer</label>
                <textarea wire:model="invoice_footer" rows="3" placeholder="e.g. Goods once sold are not returnable without a valid reason. Payment due within 15 days." class="w-full border rounded-lg p-2 mt-1"></textarea>
                <p class="text-xs text-slate-400 mt-1">Printed at the bottom of every delivery invoice PDF.</p>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">Save</button>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-6 mt-6">
        <h2 class="font-semibold mb-1">Logo</h2>
        <p class="text-sm text-slate-500 mb-4">Shown on invoice PDFs. PNG or JPG, up to 1 MB. A wide logo on a white or transparent background works best.</p>

        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="w-40 h-20 rounded-lg border bg-slate-50 flex items-center justify-center overflow-hidden shrink-0">
                @if($logo && method_exists($logo, 'temporaryUrl'))
                    <img src="{{ $logo->temporaryUrl() }}" alt="New logo" class="max-h-full max-w-full object-contain">
                @elseif($logoUrl)
                    <img src="{{ $logoUrl }}" alt="Logo" class="max-h-full max-w-full object-contain">
                @else
                    <span class="text-xs text-slate-400">No logo</span>
                @endif
            </div>
            <div class="flex-1 space-y-2">
                <input wire:model="logo" type="file" accept="image/png,image/jpeg" class="text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-slate-100 file:text-slate-700">
                @error('logo') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                <div class="flex gap-2">
                    <button wire:click="saveLogo" wire:loading.attr="disabled" @disabled(! $logo) class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium">Upload logo</button>
                    @if($logoUrl)
                        <button wire:click="removeLogo" wire:confirm="Remove the logo?" class="px-4 py-2 rounded-lg text-red-600 hover:bg-red-50 text-sm font-medium">Remove</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
