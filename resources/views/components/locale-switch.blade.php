{{-- বাংলা / EN switch. Each button is a tiny POST form (CSRF-protected). --}}
@props(['dark' => false])
<div {{ $attributes->merge(['class' => 'inline-flex items-center rounded-lg p-0.5 text-xs font-semibold '.($dark ? 'bg-slate-800' : 'bg-slate-100')]) }}>
    @foreach(\App\Http\Middleware\SetLocale::SUPPORTED as $code => $name)
        <form method="post" action="{{ route('locale.switch', $code) }}">
            @csrf
            <button type="submit"
                    class="px-2.5 py-1 rounded-md {{ app()->getLocale() === $code ? ($dark ? 'bg-indigo-600 text-white' : 'bg-white shadow text-slate-800') : ($dark ? 'text-slate-400 hover:text-white' : 'text-slate-500 hover:text-slate-800') }}"
                    @if(app()->getLocale() === $code) aria-current="true" @endif>
                {{ $code === 'bn' ? 'বাংলা' : 'EN' }}
            </button>
        </form>
    @endforeach
</div>
