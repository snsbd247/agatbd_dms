@php
    $m = fn ($v) => number_format((float) $v, 2);
    $trading = ['sales', 'sales_discount', 'sales_return'];
@endphp
<table class="lines">
    <thead><tr><th>{{ __('Particulars') }}</th><th class="r" style="width: 28%;">{{ __('Amount') }} (৳)</th></tr></thead>
    <tbody>
        <tr class="grp"><td colspan="2">{{ __('Revenue') }}</td></tr>
        @foreach(collect($data['income'])->filter(fn ($r) => in_array($r['account']->system_key, $trading, true)) as $r)
            <tr><td>&nbsp;&nbsp;{{ __($r['account']->name) }}</td><td class="r">{{ $m($r['amount']) }}</td></tr>
        @endforeach
        <tr class="tot"><td>{{ __('Net sales') }}</td><td class="r">{{ $m($data['revenue']) }}</td></tr>
        <tr><td>{{ __('Less: Cost of Goods Sold') }}</td><td class="r">({{ $m($data['cogs']) }})</td></tr>
        <tr class="tot"><td>{{ __('Gross profit') }}</td><td class="r">{{ $m($data['gross']) }}</td></tr>

        @php $other = collect($data['income'])->reject(fn ($r) => in_array($r['account']->system_key, $trading, true)); @endphp
        @if($other->isNotEmpty())
            <tr class="grp"><td colspan="2">{{ __('Other income') }}</td></tr>
            @foreach($other as $r)
                <tr><td>&nbsp;&nbsp;{{ __($r['account']->name) }}</td><td class="r">{{ $m($r['amount']) }}</td></tr>
            @endforeach
        @endif

        <tr class="grp"><td colspan="2">{{ __('Operating expenses') }}</td></tr>
        @forelse(collect($data['expense'])->reject(fn ($r) => $r['account']->system_key === 'cogs') as $r)
            <tr><td>&nbsp;&nbsp;{{ __($r['account']->name) }}</td><td class="r">{{ $m($r['amount']) }}</td></tr>
        @empty
            <tr><td class="muted">&nbsp;&nbsp;{{ __('None') }}</td><td></td></tr>
        @endforelse
        <tr class="tot"><td>{{ __('Total expenses') }}</td><td class="r">({{ $m($data['expenses']) }})</td></tr>
    </tbody>
    <tfoot>
        <tr class="tot">
            <td>{{ $data['net'] >= 0 ? __('Net profit') : __('Net loss') }}</td>
            <td class="r">{{ $m(abs($data['net'])) }}</td>
        </tr>
    </tfoot>
</table>
