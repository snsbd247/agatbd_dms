<div class="bg-white rounded-xl shadow p-8 flex flex-col items-center text-center max-w-lg mx-auto mt-4 sm:mt-12">
    <div class="w-16 h-16 rounded-2xl bg-amber-50 flex items-center justify-center mb-4">
        <x-icon name="archive-box" class="w-8 h-8 text-amber-600" />
    </div>
    <h2 class="text-xl font-semibold">Backup Data</h2>
    <p class="text-slate-500 mt-2">Download a complete copy of your data (companies, products, orders, invoices, payments, and everything else) as a single JSON file — keep it somewhere safe.</p>

    <a href="{{ route('more.backup.download') }}" class="mt-6 w-full sm:w-auto bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-3 rounded-xl font-medium flex items-center justify-center gap-2">
        <x-icon name="download" class="w-5 h-5" /> Download Backup
    </a>

    <p class="text-xs text-slate-400 mt-6">Restoring from a backup file isn't available yet — for now, contact your administrator if data ever needs to be restored.</p>
</div>
