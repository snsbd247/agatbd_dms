@php $m = fn ($v) => abs((float) $v) < 0.005 ? '' : number_format((float) $v, 2); @endphp
<table class="lines">
    <thead>
        <tr>
            <th>{{ __('Code') }}</th>
            <th>{{ __('Account') }}</th>
            <th class="r">{{ __('Opening') }}</th>
            <th class="r">{{ __('Debit') }}</th>
            <th class="r">{{ __('Credit') }}</th>
            <th class="r">{{ __('Closing Dr') }}</th>
            <th class="r">{{ __('Closing Cr') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($data['rows'] as $r)
            <tr>
                <td class="muted">{{ $r['account']->code }}</td>
                <td>{{ __($r['account']->name) }}</td>
                <td class="r">{{ abs($r['opening']) < 0.005 ? '' : number_format(abs($r['opening']), 2).($r['opening'] > 0 ? ' Dr' : ' Cr') }}</td>
                <td class="r">{{ $m($r['debit']) }}</td>
                <td class="r">{{ $m($r['credit']) }}</td>
                <td class="r">{{ $m($r['closing_debit']) }}</td>
                <td class="r">{{ $m($r['closing_credit']) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="c muted">{{ __('No entries in this period.') }}</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="tot">
            <td></td>
            <td>{{ __('Total') }} {{ abs($data['debit'] - $data['credit']) < 0.005 ? '✓' : '' }}</td>
            <td></td><td></td><td></td>
            <td class="r">{{ number_format($data['debit'], 2) }}</td>
            <td class="r">{{ number_format($data['credit'], 2) }}</td>
        </tr>
    </tfoot>
</table>
