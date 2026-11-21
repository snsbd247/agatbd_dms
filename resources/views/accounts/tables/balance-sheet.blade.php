@php
    $m = fn ($v) => number_format((float) $v, 2);
    $sum = fn ($rows) => array_sum(array_column($rows, 'amount'));
@endphp
<table class="lines">
    <thead><tr><th>{{ __('Particulars') }}</th><th class="r" style="width: 28%;">{{ __('Amount') }} (৳)</th></tr></thead>
    <tbody>
        <tr class="grp"><td colspan="2">{{ __('Assets') }}</td></tr>
        @forelse($data['assets'] as $r)
            <tr><td>&nbsp;&nbsp;{{ __($r['account']->name) }}</td><td class="r">{{ $m($r['amount']) }}</td></tr>
        @empty
            <tr><td class="muted">&nbsp;&nbsp;{{ __('None') }}</td><td></td></tr>
        @endforelse
        <tr class="tot"><td>{{ __('Total assets') }}</td><td class="r">{{ $m($data['total_assets']) }}</td></tr>

        <tr class="grp"><td colspan="2">{{ __('Liabilities') }}</td></tr>
        @forelse($data['liabilities'] as $r)
            <tr><td>&nbsp;&nbsp;{{ __($r['account']->name) }}</td><td class="r">{{ $m($r['amount']) }}</td></tr>
        @empty
            <tr><td class="muted">&nbsp;&nbsp;{{ __('None') }}</td><td></td></tr>
        @endforelse
        <tr class="tot"><td>{{ __('Total liabilities') }}</td><td class="r">{{ $m($sum($data['liabilities'])) }}</td></tr>

        <tr class="grp"><td colspan="2">{{ __('Equity') }}</td></tr>
        @foreach($data['equity'] as $r)
            <tr><td>&nbsp;&nbsp;{{ __($r['account']->name) }}</td><td class="r">{{ $m($r['amount']) }}</td></tr>
        @endforeach
        <tr><td>&nbsp;&nbsp;{{ __('Profit / loss not yet closed') }}</td><td class="r">{{ $m($data['earnings']) }}</td></tr>
        <tr class="tot"><td>{{ __('Total equity') }}</td><td class="r">{{ $m($sum($data['equity']) + $data['earnings']) }}</td></tr>
    </tbody>
    <tfoot>
        <tr class="tot">
            <td>{{ __('Total liabilities & equity') }} {{ abs($data['total_assets'] - $data['total_liabilities_equity']) < 0.005 ? '✓' : '' }}</td>
            <td class="r">{{ $m($data['total_liabilities_equity']) }}</td>
        </tr>
    </tfoot>
</table>
