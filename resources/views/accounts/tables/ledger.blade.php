@php $m = fn ($v) => abs((float) $v) < 0.005 ? '' : number_format((float) $v, 2); @endphp
@if(! $data['account'])
    <p class="muted">{{ __('Choose an account to see its ledger.') }}</p>
@else
<table class="lines">
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Voucher') }}</th>
            <th>{{ __('Particulars') }}</th>
            <th class="r">{{ __('Debit') }}</th>
            <th class="r">{{ __('Credit') }}</th>
            <th class="r">{{ __('Balance') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr class="grp"><td colspan="5">{{ __('Opening balance') }}</td><td class="r">{{ number_format($data['opening'], 2) }}</td></tr>
        @forelse($data['rows'] as $row)
            @php $l = $row['line']; @endphp
            <tr>
                <td class="muted">{{ \Carbon\Carbon::parse($l->entry_date)->format('d M Y') }}</td>
                <td>@if(! empty($links))<a href="{{ route('accounts.vouchers.show', $l->entry_id) }}">{{ $l->number }}</a>@else{{ $l->number }}@endif</td>
                <td>
                    {{ $l->narration ?: $l->entry_narration }}
                    @if($l->party_type && empty($partyFixed))<span class="muted"> · {{ \App\Models\Accounts\JournalLine::partyLabel($l->party_type, $l->party_id) }}</span>@endif
                </td>
                <td class="r">{{ $m($l->debit) }}</td>
                <td class="r">{{ $m($l->credit) }}</td>
                <td class="r">{{ number_format($row['balance'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="c muted">{{ __('No entries in this period.') }}</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="tot">
            <td colspan="3">{{ __('Closing balance') }}</td>
            <td class="r">{{ number_format($data['debit'], 2) }}</td>
            <td class="r">{{ number_format($data['credit'], 2) }}</td>
            <td class="r">{{ number_format($data['closing'], 2) }}</td>
        </tr>
    </tfoot>
</table>
@endif
