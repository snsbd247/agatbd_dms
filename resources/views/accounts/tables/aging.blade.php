@php $m = fn ($v) => abs((float) $v) < 0.005 ? '' : number_format((float) $v, 2); @endphp
<table class="lines">
    <thead>
        <tr>
            <th>{{ __('Party') }}</th>
            <th class="r">{{ __('0–30 days') }}</th>
            <th class="r">{{ __('31–60 days') }}</th>
            <th class="r">{{ __('61–90 days') }}</th>
            <th class="r">{{ __('Over 90 days') }}</th>
            <th class="r">{{ __('Advance') }}</th>
            <th class="r">{{ __('Balance') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($data['rows'] as $r)
            <tr>
                <td>{{ $r['name'] }}</td>
                <td class="r">{{ $m($r['buckets']['0-30']) }}</td>
                <td class="r">{{ $m($r['buckets']['31-60']) }}</td>
                <td class="r">{{ $m($r['buckets']['61-90']) }}</td>
                <td class="r">{{ $m($r['buckets']['90+']) }}</td>
                <td class="r">{{ $m($r['advance']) }}</td>
                <td class="r"><strong>{{ number_format($r['balance'], 2) }}</strong></td>
            </tr>
        @empty
            <tr><td colspan="7" class="c muted">{{ __('Nothing outstanding.') }}</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="tot">
            <td>{{ __('Total') }}</td>
            <td class="r">{{ $m($data['buckets']['0-30']) }}</td>
            <td class="r">{{ $m($data['buckets']['31-60']) }}</td>
            <td class="r">{{ $m($data['buckets']['61-90']) }}</td>
            <td class="r">{{ $m($data['buckets']['90+']) }}</td>
            <td></td>
            <td class="r">{{ number_format($data['total'], 2) }}</td>
        </tr>
    </tfoot>
</table>
