@extends('pdfs.layout')

@section('docTitle', $entry->number)

@section('body')
@include('pdfs.partials.head', [
    'title' => $entry->typeLabel(),
    'meta' => array_filter([
        __('No.').': '.$entry->number,
        __('Date').': '.$entry->entry_date->format('d M Y'),
        $entry->status === 'draft' ? __('DRAFT — not posted') : null,
    ]),
])

@if($entry->narration)
    <p style="margin: 2mm 0 4mm;">{{ $entry->narration }}</p>
@endif

<table class="lines">
    <thead>
        <tr>
            <th style="width: 12%;">{{ __('Code') }}</th>
            <th>{{ __('Account') }}</th>
            <th>{{ __('Party') }} / {{ __('Note') }}</th>
            <th class="r" style="width: 16%;">{{ __('Debit') }} ({{ $currency }})</th>
            <th class="r" style="width: 16%;">{{ __('Credit') }} ({{ $currency }})</th>
        </tr>
    </thead>
    <tbody>
        @foreach($entry->lines as $l)
            <tr>
                <td class="muted">{{ $l->account->code }}</td>
                <td>{{ __($l->account->name) }}</td>
                <td>{{ collect([$l->partyName(), $l->narration])->filter()->implode(' · ') }}</td>
                <td class="r">{{ (float) $l->debit > 0 ? number_format($l->debit, 2) : '' }}</td>
                <td class="r">{{ (float) $l->credit > 0 ? number_format($l->credit, 2) : '' }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="r"><strong>{{ __('Total') }}</strong></td>
            <td class="r"><strong>{{ number_format($entry->lines->sum('debit'), 2) }}</strong></td>
            <td class="r"><strong>{{ number_format($entry->lines->sum('credit'), 2) }}</strong></td>
        </tr>
    </tfoot>
</table>

<p style="margin-top: 3mm;">{{ __('In words') }}: {{ \App\Services\PdfService::amountInWords((float) $entry->lines->sum('debit')) }}</p>

@include('pdfs.partials.signatures', ['labels' => [__('Prepared by').($entry->creator ? ': '.$entry->creator->name : ''), __('Checked by'), __('Approved by').($entry->approver ? ': '.$entry->approver->name : '')]])
@endsection
