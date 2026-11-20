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
                <textarea wire:model="invoice_footer" rows="3" class="w-full border rounded-lg p-2 mt-1"></textarea>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">Save</button>
        </div>
    </div>
</div>
