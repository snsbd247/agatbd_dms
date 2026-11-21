{{-- Light / dark switch. The choice is kept in this browser (localStorage), so each device can differ. --}}
<button type="button"
    x-data="{ dark: document.documentElement.classList.contains('dark') }"
    @click="dark = !dark; window.dmsSetTheme(dark ? 'dark' : 'light')"
    x-bind:title="dark ? @js(__('Light mode')) : @js(__('Dark mode'))"
    {{ $attributes->merge(['class' => 'text-slate-500 hover:text-slate-900 p-1.5 rounded-lg hover:bg-slate-100']) }}>
    <x-icon name="moon" class="w-5 h-5" x-show="!dark" />
    <x-icon name="sun" class="w-5 h-5" x-show="dark" x-cloak />
</button>
