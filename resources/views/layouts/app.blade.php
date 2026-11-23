<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ \App\Support\Brand::name() }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" href="{{ asset('icon-192.png') }}">
    <title>{{ \App\Support\Brand::title(isset($title) ? __($title) : null) }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Apply the saved theme before the page paints, so there is no white flash.
        (function () {
            try {
                var t = localStorage.getItem('dms_theme');
                var dark = t === 'dark' || (t !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-100 text-slate-900 overscroll-none">
    @php
        $menu = \App\Support\Navigation::for(auth()->user());

        $unreadNotifications = auth()->user()->unreadNotifications()->latest()->take(5)->get();
        $unreadNotificationCount = auth()->user()->unreadNotifications()->count();

        $activeGroupLabel = \App\Support\Navigation::activeGroupLabel($menu);

        $dashboardRouteName = \App\Http\Controllers\DashboardController::homeRoute(auth()->user());
        $dashboardHref = route($dashboardRouteName);
        $dashboardActive = request()->routeIs('dashboard') || request()->routeIs($dashboardRouteName) || request()->routeIs('dashboard.factory', 'dashboard.accounts', 'dashboard.hr', 'dashboard.dealer');

        $masterTabVisible = auth()->user()->can('master-data.view');
        $businessTabVisible = collect(['purchase.view', 'inventory.view', 'sales.view', 'delivery.view', 'collection.view'])
            ->contains(fn ($p) => auth()->user()->can($p));
        $reportsTabVisible = auth()->user()->can('reports.view');

        $masterTabActive = request()->routeIs('master-data.*');
        $businessTabActive = collect(['purchase.', 'inventory.', 'sales.', 'delivery.', 'collection.'])->contains(fn ($p) => request()->routeIs($p.'*'))
            || request()->routeIs('reports.stock') || request()->routeIs('business.*');
        $reportsTabActive = request()->routeIs('reports.*') && ! request()->routeIs('reports.stock');
        $portalTabVisible = auth()->user()->hasRole('Retailer');
        $portalTabActive = request()->routeIs('portal.orders*', 'portal.statement');
        $moreTabActive = ! $dashboardActive && ! $masterTabActive && ! $businessTabActive && ! $reportsTabActive && ! $portalTabActive;
    @endphp

    <div class="min-h-screen flex" x-data="{ collapsed: localStorage.getItem('dms_sidebar_collapsed') === '1' }" x-init="$watch('collapsed', value => localStorage.setItem('dms_sidebar_collapsed', value ? '1' : '0'))">

        <aside
            class="dark-keep hidden lg:flex lg:flex-col bg-slate-950 text-slate-200 lg:shrink-0 lg:transition-all"
            :class="{
                'lg:w-20': collapsed,
                'lg:w-64': !collapsed,
            }"
        >
            <div class="p-5 border-b border-slate-800 flex items-center gap-3 shrink-0">
                <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center shrink-0 overflow-hidden">
                    <img src="{{ asset('icon-192.png') }}" alt="" class="w-9 h-9 object-contain">
                </div>
                <div x-show="!collapsed" x-cloak>
                    <p class="text-base font-bold text-white leading-tight truncate max-w-[10.5rem]" title="{{ \App\Support\Brand::name() }}">{{ \App\Support\Brand::name() }}</p>
                    <p class="text-[11px] text-slate-500 truncate max-w-[10.5rem]">{{ auth()->user()->dealer?->name ?? __('Dealer Management System') }}</p>
                </div>
            </div>

            <nav class="flex-1 p-3 space-y-1 text-sm overflow-y-auto">
                <a href="{{ $dashboardHref }}" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800/70 {{ $dashboardActive ? 'bg-indigo-600 text-white' : 'text-slate-300' }}">
                    <x-icon name="grid" class="w-5 h-5 shrink-0" />
                    <span x-show="!collapsed" x-cloak>{{ __('Dashboard') }}</span>
                </a>
                <a href="{{ route('notifications.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800/70 {{ request()->routeIs('notifications.index') ? 'bg-indigo-600 text-white' : 'text-slate-300' }}">
                    <x-icon name="bell" class="w-5 h-5 shrink-0" />
                    <span x-show="!collapsed" x-cloak>{{ __('Notifications') }}</span>
                </a>

                @foreach($menu as $section)
                    @if($section['label'] !== '')
                        <p class="px-3 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500" x-show="!collapsed" x-cloak>{{ __($section['label']) }}</p>
                        <div class="mx-3 my-2 border-t border-slate-800" x-show="collapsed" x-cloak></div>
                    @endif
                    @foreach($section['groups'] as $group)
                        <div x-data="{ open: {{ $group['active'] ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" title="{{ __($group['label']) }}" class="w-full flex items-center justify-between gap-3 px-3 py-2 rounded-lg hover:bg-slate-800/70 {{ $group['active'] ? 'text-white' : 'text-slate-300' }}">
                                <span class="flex items-center gap-3">
                                    <x-icon :name="$group['icon']" class="w-5 h-5 shrink-0" />
                                    <span x-show="!collapsed" x-cloak>{{ __($group['label']) }}</span>
                                </span>
                                <x-icon name="chevron-down" class="w-4 h-4 shrink-0 transition-transform" x-show="!collapsed" x-cloak x-bind:class="open ? 'rotate-180' : ''" />
                            </button>
                            <div class="pl-4 mt-1 space-y-0.5 border-l border-slate-800 ml-6" x-show="open && !collapsed" x-cloak>
                                @foreach($group['links'] as $link)
                                    <a href="{{ $link['href'] }}" class="flex items-center gap-2 pl-3 pr-3 py-1.5 rounded-lg text-sm {{ $link['active'] ? 'bg-indigo-600/20 text-white border-l-2 border-indigo-500 -ml-px' : 'text-slate-400 hover:bg-slate-800/70 hover:text-slate-200' }}">
                                        <x-icon :name="$link['icon'] ?? 'chevron-right'" class="w-4 h-4 shrink-0" />
                                        <span>{{ __($link['label']) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endforeach

                <a href="{{ route('help') }}" class="flex items-center gap-3 px-3 py-2 mt-3 rounded-lg hover:bg-slate-800/70 {{ request()->routeIs('help') ? 'bg-indigo-600 text-white' : 'text-slate-300' }}">
                    <x-icon name="question-mark-circle" class="w-5 h-5 shrink-0" />
                    <span x-show="!collapsed" x-cloak>{{ __('Help & User Guide') }}</span>
                </a>
            </nav>

            <div class="p-3 shrink-0" x-show="!collapsed" x-cloak>
                <div class="bg-slate-900 rounded-xl p-4 text-center">
                    <div class="w-10 h-10 rounded-full bg-indigo-600/20 text-indigo-400 flex items-center justify-center mx-auto mb-2">
                        <x-icon name="headset" class="w-5 h-5" />
                    </div>
                    <p class="text-sm font-medium text-white">{{ __('Need Help?') }}</p>
                    <p class="text-xs text-slate-500 mt-0.5 mb-3">{{ __('Contact our support team') }}</p>
                    <a href="mailto:support@syncsolutionbd.com" class="block w-full bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium rounded-lg py-2">{{ __('Contact Support') }}</a>
                </div>
            </div>

            <div class="p-4 border-t border-slate-800 shrink-0">
                <div class="flex items-center gap-3" x-data="{ menuOpen: false }" @click.outside="menuOpen = false">
                    <button type="button" @click="menuOpen = !menuOpen" class="flex items-center gap-3 flex-1 min-w-0">
                        <div class="relative shrink-0">
                            <div class="w-9 h-9 rounded-full bg-slate-800 text-slate-300 flex items-center justify-center">
                                <x-icon name="user" class="w-5 h-5" />
                            </div>
                            <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-green-500 border-2 border-slate-950"></span>
                        </div>
                        <div class="min-w-0 text-left" x-show="!collapsed" x-cloak>
                            <p class="text-sm text-slate-200 truncate">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500">{{ auth()->user()->getRoleNames()->first() }}</p>
                        </div>
                        <x-icon name="chevron-down" class="w-4 h-4 text-slate-500 shrink-0" x-show="!collapsed" x-cloak />
                    </button>
                </div>
                <div class="mt-3 space-y-2" x-show="!collapsed" x-cloak>
                    <a href="{{ route('account.change-password') }}" class="text-xs text-slate-400 hover:text-white flex items-center gap-1.5">
                        <x-icon name="cog" class="w-4 h-4" /> {{ __('Change Password') }}
                    </a>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-xs text-slate-400 hover:text-white flex items-center gap-1.5">
                            <x-icon name="logout" class="w-4 h-4" /> {{ __('Logout') }}
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">
            <header class="sticky top-0 z-30 bg-white border-b px-4 sm:px-6 py-3 flex items-center justify-between gap-4 pt-safe">
                <div class="flex items-center gap-4 min-w-0">
                    <button type="button" @click="collapsed = !collapsed" class="hidden lg:inline-flex text-slate-500 hover:text-slate-900 shrink-0">
                        <x-icon name="menu" class="w-6 h-6" />
                    </button>
                    <div class="min-w-0">
                        <h1 class="text-base sm:text-xl font-semibold truncate leading-tight">{{ isset($title) ? __($title) : \App\Support\Brand::name() }}</h1>
                        <p class="text-xs text-slate-400 truncate hidden sm:block">
                            @if($activeGroupLabel)
                                {{ __($activeGroupLabel) }} <span class="mx-1">&gt;</span> {{ __($title ?? '') }}
                            @else
                                {{ __($title ?? '') }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
                    @unless(request()->routeIs('search'))
                        <div class="hidden md:block">
                            <livewire:global-search />
                        </div>
                    @endunless
                    <a href="{{ route('search') }}" class="md:hidden text-slate-500 hover:text-slate-900 p-1.5 rounded-lg hover:bg-slate-100" title="{{ __('Search') }}">
                        <x-icon name="search" class="w-5 h-5" />
                    </a>

                    {{ $headerActions ?? '' }}

                    <x-locale-switch class="hidden sm:inline-flex" />

                    <x-theme-toggle />

                    <button type="button" onclick="window.dmsExportTable(@js(isset($title) ? __($title) : 'export'))" class="hidden sm:inline-flex text-slate-500 hover:text-slate-900 p-1.5 rounded-lg hover:bg-slate-100" title="{{ __('Download as Excel (CSV)') }}">
                        <x-icon name="download" class="w-5 h-5" />
                    </button>
                    <button type="button" onclick="window.print()" class="hidden sm:inline-flex text-slate-500 hover:text-slate-900 p-1.5 rounded-lg hover:bg-slate-100" title="{{ __('Print this page') }}">
                        <x-icon name="printer" class="w-5 h-5" />
                    </button>

                    <button type="button" onclick="window.location.reload()" class="hidden sm:inline-flex text-slate-500 hover:text-slate-900 p-1.5 rounded-lg hover:bg-slate-100" title="{{ __('Refresh') }}">
                        <x-icon name="refresh" class="w-5 h-5" />
                    </button>
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="relative text-slate-500 hover:text-slate-900 p-1.5 rounded-lg hover:bg-slate-100" title="{{ $unreadNotificationCount }} unread notification(s)">
                            <x-icon name="bell" class="w-5 h-5" />
                            @if($unreadNotificationCount > 0)
                                <span class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] leading-none rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center">{{ $unreadNotificationCount }}</span>
                            @endif
                        </button>
                        <div x-show="open" x-cloak class="absolute right-0 mt-2 w-80 max-w-[90vw] bg-white rounded-xl shadow-2xl border z-50">
                            <div class="px-4 py-3 border-b flex items-center justify-between">
                                <span class="font-semibold text-sm">{{ __('Notifications') }}</span>
                                @if($unreadNotificationCount > 0)
                                    <a href="{{ route('notifications.index') }}" class="text-xs text-indigo-600 hover:underline">{{ __('View all') }}</a>
                                @endif
                            </div>
                            <div class="max-h-80 overflow-y-auto divide-y">
                                @forelse($unreadNotifications as $notification)
                                    <a href="{{ route('notifications.open', $notification->id) }}" class="block px-4 py-3 hover:bg-slate-50">
                                        <p class="text-sm font-medium">{{ __($notification->data['title'] ?? 'Notification') }}</p>
                                        <p class="text-xs text-slate-500 mt-0.5">{{ $notification->data['message'] ?? '' }}</p>
                                        <p class="text-xs text-slate-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                                    </a>
                                @empty
                                    <p class="px-4 py-6 text-center text-sm text-slate-400">{{ __('No unread notifications.') }}</p>
                                @endforelse
                            </div>
                            <div class="px-4 py-2 border-t">
                                <a href="{{ route('notifications.index') }}" class="block text-center text-xs text-slate-500 hover:text-indigo-600 py-1">{{ __('View all notifications') }}</a>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('help') }}" class="hidden sm:inline-flex p-1.5 rounded-lg hover:bg-slate-100 {{ request()->routeIs('help') ? 'text-indigo-600' : 'text-slate-500 hover:text-slate-900' }}" title="{{ __('Help') }}">
                        <x-icon name="question-mark-circle" class="w-5 h-5" />
                    </a>

                    <div class="relative pl-0 sm:pl-3 sm:border-l" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="window.innerWidth >= 1024 ? (open = !open) : (window.location.href = '{{ route('account.profile') }}')" class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center shrink-0">
                                <x-icon name="user" class="w-4 h-4" />
                            </div>
                            <div class="hidden sm:block text-left leading-tight">
                                <p class="text-sm font-medium">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-slate-400">{{ auth()->user()->getRoleNames()->first() }}</p>
                            </div>
                            <x-icon name="chevron-down" class="w-4 h-4 text-slate-400 hidden sm:block" />
                        </button>
                        <div x-show="open" x-cloak class="absolute right-0 mt-2 w-48 max-w-[90vw] bg-white rounded-xl shadow-2xl border z-50 py-1">
                            <a href="{{ route('account.change-password') }}" class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">{{ __('Change Password') }}</a>
                            @can('settings.manage')
                                <a href="{{ route('settings.company-profile') }}" class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">{{ __('Settings') }}</a>
                            @endcan
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">{{ __('Logout') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>
            <main class="flex-1 p-4 sm:p-6 max-w-7xl w-full mx-auto pb-safe-nav lg:pb-6">
                <div class="print-only mb-3 border-b pb-2"><p class="text-lg font-bold">{{ \App\Support\Brand::name() }}</p><p class="text-sm">{{ isset($title) ? __($title) : '' }} · {{ __('Printed') }} {{ now()->format('d M Y H:i') }}</p></div>
                {{ $slot }}
            </main>
        </div>

        <nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t flex items-stretch pb-safe">
            <a href="{{ $dashboardHref }}" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 {{ $dashboardActive ? 'text-indigo-600' : 'text-slate-500' }}">
                <x-icon name="grid" class="w-5 h-5" />
                <span class="text-[10px] font-medium">{{ __('Home') }}</span>
            </a>
            @if($portalTabVisible)
                <a href="{{ route('portal.orders') }}" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 {{ $portalTabActive && ! request()->routeIs('portal.statement') ? 'text-indigo-600' : 'text-slate-500' }}">
                    <x-icon name="shopping-bag" class="w-5 h-5" />
                    <span class="text-[10px] font-medium">{{ __('Orders') }}</span>
                </a>
                <a href="{{ route('portal.statement') }}" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 {{ request()->routeIs('portal.statement') ? 'text-indigo-600' : 'text-slate-500' }}">
                    <x-icon name="book-open" class="w-5 h-5" />
                    <span class="text-[10px] font-medium">{{ __('Statement') }}</span>
                </a>
            @endif
            @if($masterTabVisible)
                <a href="{{ route('master-data.index') }}" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 {{ $masterTabActive ? 'text-indigo-600' : 'text-slate-500' }}">
                    <x-icon name="database" class="w-5 h-5" />
                    <span class="text-[10px] font-medium">{{ __('Master') }}</span>
                </a>
            @endif
            @if($businessTabVisible)
                <a href="{{ route('business.index') }}" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 {{ $businessTabActive ? 'text-indigo-600' : 'text-slate-500' }}">
                    <x-icon name="shopping-cart" class="w-5 h-5" />
                    <span class="text-[10px] font-medium">{{ __('Business') }}</span>
                </a>
            @endif
            @if($reportsTabVisible)
                <a href="{{ route('reports.index') }}" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 {{ $reportsTabActive ? 'text-indigo-600' : 'text-slate-500' }}">
                    <x-icon name="chart-bar" class="w-5 h-5" />
                    <span class="text-[10px] font-medium">{{ __('Reports') }}</span>
                </a>
            @endif
            <a href="{{ route('more.index') }}" class="relative flex-1 flex flex-col items-center justify-center gap-0.5 py-2 {{ $moreTabActive ? 'text-indigo-600' : 'text-slate-500' }}">
                <span class="relative">
                    <x-icon name="dots-vertical" class="w-5 h-5" />
                    @if($unreadNotificationCount > 0)
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[9px] leading-none rounded-full h-3.5 min-w-[0.875rem] px-0.5 flex items-center justify-center">{{ $unreadNotificationCount }}</span>
                    @endif
                </span>
                <span class="text-[10px] font-medium">{{ __('More') }}</span>
            </a>
        </nav>
    </div>

    @livewireScripts
</body>
</html>
