@props(['icon', 'title', 'description'])

<div class="bg-white rounded-xl shadow p-12 flex flex-col items-center text-center max-w-lg mx-auto mt-12">
    <div class="w-16 h-16 rounded-2xl bg-indigo-50 flex items-center justify-center mb-4">
        <x-icon :name="$icon" class="w-8 h-8 text-indigo-600" />
    </div>
    <h2 class="text-xl font-semibold">{{ $title }}</h2>
    <p class="text-slate-500 mt-2">{{ $description }}</p>
    <span class="mt-4 px-3 py-1 rounded-full text-xs bg-amber-100 text-amber-700 font-medium">{{ __('Coming soon') }}</span>
</div>
