{{-- One-line messages after an action (Livewire re-renders only the component, so lists include this themselves). --}}
@if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-2 mb-4">{{ session('error') }}</div>
@endif
@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-2 mb-4">{{ session('success') }}</div>
@endif
