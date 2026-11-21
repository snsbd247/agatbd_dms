<div>
    <div class="flex items-center justify-between mb-4">
        <div class="flex gap-2 text-sm">
            <button wire:click="$set('filter', 'all')" class="px-3 py-1.5 rounded-lg {{ $filter === 'all' ? 'bg-indigo-600 text-white' : 'bg-white border text-slate-600' }}">{{ __('All') }}</button>
            <button wire:click="$set('filter', 'unread')" class="px-3 py-1.5 rounded-lg {{ $filter === 'unread' ? 'bg-indigo-600 text-white' : 'bg-white border text-slate-600' }}">Unread ({{ $unreadCount }})</button>
        </div>
        @if($unreadCount > 0)
            <button wire:click="markAllRead" class="text-sm text-indigo-600 hover:underline">{{ __('Mark all as read') }}</button>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden divide-y">
        @forelse($notifications as $notification)
            <button wire:click="open('{{ $notification->id }}')" wire:key="notif-{{ $notification->id }}" class="w-full text-left px-4 py-3 flex items-start gap-3 hover:bg-slate-50 {{ $notification->read_at ? '' : 'bg-indigo-50/50' }}">
                <div class="w-9 h-9 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0">
                    <x-icon name="bell" class="w-4 h-4" />
                </div>
                <span class="flex-1 min-w-0">
                    <span class="block font-medium text-sm">{{ __($notification->data['title'] ?? 'Notification') }}</span>
                    <span class="block text-sm text-slate-500">{{ $notification->data['message'] ?? '' }}</span>
                    <span class="block text-xs text-slate-400 mt-1">{{ $notification->created_at->diffForHumans() }}</span>
                </span>
                <span class="w-2 h-2 mt-1.5 rounded-full shrink-0 {{ $notification->read_at ? 'bg-transparent' : 'bg-indigo-500' }}"></span>
            </button>
        @empty
            <div class="px-4 py-8 text-center text-slate-400">No notifications{{ $filter === 'unread' ? ' unread' : '' }}.</div>
        @endforelse
    </div>

    <x-pagination :paginator="$notifications" />
</div>
