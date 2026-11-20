<div class="max-w-lg">
    <div class="bg-white rounded-xl shadow p-6 flex items-center gap-4 mb-4">
        <div class="w-16 h-16 rounded-2xl bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
            <x-icon name="user" class="w-8 h-8" />
        </div>
        <div class="min-w-0">
            <h2 class="text-lg font-bold truncate">{{ auth()->user()->name }}</h2>
            <p class="text-slate-500 text-sm">{{ auth()->user()->getRoleNames()->first() }}</p>
            <p class="text-slate-400 text-sm truncate">{{ auth()->user()->email }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden divide-y">
        <a href="{{ route('account.change-password') }}" class="flex items-center gap-4 p-4 hover:bg-slate-50">
            <x-icon name="lock-closed" class="w-5 h-5 text-slate-400 shrink-0" />
            <span class="flex-1 font-medium">Change Password</span>
            <x-icon name="chevron-right" class="w-5 h-5 text-slate-300 shrink-0" />
        </a>
        <a href="{{ route('account.notification-settings') }}" class="flex items-center gap-4 p-4 hover:bg-slate-50">
            <x-icon name="bell" class="w-5 h-5 text-slate-400 shrink-0" />
            <span class="flex-1 font-medium">Notification Settings</span>
            <x-icon name="chevron-right" class="w-5 h-5 text-slate-300 shrink-0" />
        </a>
        <div class="flex items-center gap-4 p-4">
            <x-icon name="globe" class="w-5 h-5 text-slate-400 shrink-0" />
            <span class="flex-1 font-medium">Language</span>
            <span class="text-sm text-slate-500">English</span>
        </div>
        <div class="flex items-center gap-4 p-4">
            <x-icon name="question-mark-circle" class="w-5 h-5 text-slate-400 shrink-0" />
            <span class="flex-1 font-medium">App Theme</span>
            <span class="text-sm text-slate-500">Light</span>
        </div>
        <a href="{{ route('more.about') }}" class="flex items-center gap-4 p-4 hover:bg-slate-50">
            <x-icon name="box" class="w-5 h-5 text-slate-400 shrink-0" />
            <span class="flex-1 font-medium">App Version</span>
            <span class="text-sm text-slate-500">1.0.0</span>
        </a>
    </div>

    <form method="post" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button class="w-full flex items-center justify-center gap-2 bg-red-50 hover:bg-red-100 text-red-600 font-medium rounded-xl py-3">
            <x-icon name="logout" class="w-5 h-5" /> Logout
        </button>
    </form>
</div>
