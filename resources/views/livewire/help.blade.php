<div class="max-w-4xl" x-data="{ q: '' }">
    <div class="dark-keep rounded-2xl bg-gradient-to-r from-slate-800 to-indigo-800 p-6 text-white shadow-lg mb-5">
        <h2 class="text-xl font-bold">DMS ব্যবহারের নির্দেশিকা</h2>
        <p class="mt-1 text-sm text-indigo-200">আপনার কাজের অংশগুলো নিচে দেওয়া আছে। কিছু খুঁজতে নিচের বক্সে লিখুন।</p>
        <input x-model="q" type="search" placeholder="যেমন: ডিসকাউন্ট, ব্যাকআপ, stock…" class="mt-4 w-full rounded-lg px-4 py-2.5 text-slate-800 text-sm">
    </div>

    <div class="flex flex-wrap gap-2 mb-5" x-show="q === ''">
        @foreach($sections as $s)
            <a href="#help-{{ $s['id'] }}" class="bg-white border rounded-full px-3 py-1.5 text-xs font-medium text-slate-600 hover:border-indigo-300 hover:text-indigo-700 flex items-center gap-1.5">
                <x-icon :name="$s['icon']" class="w-3.5 h-3.5" /> {{ $s['title'] }}
            </a>
        @endforeach
    </div>

    <div class="space-y-4">
        @foreach($sections as $s)
            @php $haystack = mb_strtolower(strip_tags($s['title'].' '.implode(' ', $s['steps']).' '.implode(' ', $s['tips'] ?? []))); @endphp
            <section id="help-{{ $s['id'] }}" class="bg-white rounded-xl shadow p-5 scroll-mt-24"
                     data-text="{{ $haystack }}"
                     x-show="q === '' || $el.dataset.text.includes(q.toLowerCase())">
                <h3 class="font-semibold text-lg flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center"><x-icon :name="$s['icon']" class="w-4 h-4" /></span>
                    {{ $s['title'] }}
                </h3>
                <ol class="mt-3 space-y-2 text-sm text-slate-700 list-decimal pl-5 marker:text-slate-400">
                    @foreach($s['steps'] as $step)
                        <li>{!! $step !!}</li>
                    @endforeach
                </ol>
                @if(! empty($s['tips']))
                    <div class="mt-3 bg-amber-50 border border-amber-100 rounded-lg p-3 text-sm text-amber-900 space-y-1">
                        @foreach($s['tips'] as $tip)
                            <p>💡 {!! $tip !!}</p>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    <div class="dark-keep mt-6 bg-slate-900 text-slate-200 rounded-xl p-5 text-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p>আরও সাহায্য লাগলে সাপোর্ট টিমের সাথে যোগাযোগ করুন।</p>
        <a href="mailto:support@syncsolutionbd.com" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-center font-medium">Contact Support</a>
    </div>
</div>
