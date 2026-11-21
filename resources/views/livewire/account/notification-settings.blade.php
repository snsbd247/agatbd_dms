<div class="max-w-lg">
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-semibold mb-1">Notification Settings</h2>
        <p class="text-sm text-slate-500 mb-4">Choose which in-app notifications you want to receive.</p>

        @if($saved)
            <div class="bg-green-50 text-green-700 text-sm rounded-lg px-4 py-2 mb-4">Preferences saved.</div>
        @endif

        <div class="divide-y">
            <label class="flex items-center justify-between gap-4 py-3 cursor-pointer">
                <div>
                    <p class="font-medium text-sm">Pending Sales Orders</p>
                    <p class="text-xs text-slate-400">A new sales order needs approval or is on credit hold</p>
                </div>
                <input wire:model="sales_order_pending" type="checkbox" class="w-5 h-5 rounded accent-indigo-600 shrink-0">
            </label>
            <label class="flex items-center justify-between gap-4 py-3 cursor-pointer">
                <div>
                    <p class="font-medium text-sm">Return Requests</p>
                    <p class="text-xs text-slate-400">A retailer has submitted a new return request</p>
                </div>
                <input wire:model="return_request_pending" type="checkbox" class="w-5 h-5 rounded accent-indigo-600 shrink-0">
            </label>
            <label class="flex items-center justify-between gap-4 py-3 cursor-pointer">
                <div>
                    <p class="font-medium text-sm">Warranty Claims</p>
                    <p class="text-xs text-slate-400">A new warranty claim needs review</p>
                </div>
                <input wire:model="warranty_claim_pending" type="checkbox" class="w-5 h-5 rounded accent-indigo-600 shrink-0">
            </label>
            <label class="flex items-center justify-between gap-4 py-3 cursor-pointer">
                <div>
                    <p class="font-medium text-sm">Low Stock Alerts</p>
                    <p class="text-xs text-slate-400">A product's stock has dropped to or below its reorder level</p>
                </div>
                <input wire:model="low_stock_alert" type="checkbox" class="w-5 h-5 rounded accent-indigo-600 shrink-0">
            </label>
            <label class="flex items-center justify-between gap-4 py-3 cursor-pointer border-t mt-2 pt-4">
                <div>
                    <p class="font-medium text-sm">Also send by email</p>
                    <p class="text-xs text-slate-400">
                        @if($mailEnabled)
                            The notifications you turned on above are also emailed to {{ auth()->user()->email }}.
                        @else
                            Email is not set up yet (Settings → Email &amp; SMS), so for now notifications appear only in the app.
                        @endif
                    </p>
                </div>
                <input wire:model="email" type="checkbox" class="w-5 h-5 rounded accent-indigo-600 shrink-0">
            </label>
        </div>

        <div class="mt-6 flex justify-end">
            <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">Save Preferences</button>
        </div>
    </div>
</div>
