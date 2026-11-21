@php
    $m = fn ($v) => ($v < 0 ? '(' : '').number_format(abs((float) $v), 2).($v < 0 ? ')' : '');
    $names = ['operating' => __('Operating activities'), 'investing' => __('Investing activities'), 'financing' => __('Financing activities')];
@endphp
<table class="lines">
    <thead><tr><th>{{ __('Particulars') }}</th><th class="r" style="width: 28%;">{{ __('Amount') }} (৳)</th></tr></thead>
    <tbody>
        <tr class="tot"><td>{{ __('Cash, bank and mobile money at the start') }}</td><td class="r">{{ $m($data['opening']) }}</td></tr>
        @foreach($data['sections'] as $key => $section)
            <tr class="grp"><td colspan="2">{{ $names[$key] }}</td></tr>
            @forelse($section['rows'] as $label => $amount)
                <tr><td>&nbsp;&nbsp;{{ $label }}</td><td class="r">{{ $m($amount) }}</td></tr>
            @empty
                <tr><td class="muted">&nbsp;&nbsp;{{ __('None') }}</td><td></td></tr>
            @endforelse
            <tr class="tot"><td>{{ __('Net cash from :what', ['what' => mb_strtolower($names[$key])]) }}</td><td class="r">{{ $m($section['total']) }}</td></tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="tot"><td>{{ __('Cash, bank and mobile money at the end') }}</td><td class="r">{{ $m($data['closing']) }}</td></tr>
    </tfoot>
</table>
