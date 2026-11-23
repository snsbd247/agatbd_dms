{{--
    Generic printable document (sales order, purchase order, GRN, challan,
    returns, transfers ...). Filled by DocumentPdfController with:
      title, number, date, meta[], status
      parties: [[title, lines[]], ...]          up to three boxes
      columns: [[label, align ('l'|'r'|'c'), width%], ...]
      rows:    [[cell, cell, ...], ...]
      totals:  [[label, value, grand?], ...]
      words:   amount for "in words" (optional)
      note:    free text (optional)
      signatures: [label, ...]
--}}
@extends('pdfs.layout')

@section('docTitle', $doc['number'])

@section('body')
@include('pdfs.partials.head', [
    'title' => $doc['title'],
    'meta' => array_values(array_filter(array_merge([
        __('No.').': '.$doc['number'],
        __('Date').': '.$doc['date'],
        ! empty($doc['status']) ? __('Status').': '.$doc['status'] : null,
    ], $doc['meta'] ?? []))),
])

@if(! empty($doc['parties']))
    <table class="plain" style="margin-bottom: 4mm;">
        <tr>
            @foreach($doc['parties'] as [$ptitle, $plines])
                <td style="width: {{ floor(100 / count($doc['parties'])) }}%; padding-right: 4mm;">
                    <div class="box-title">{{ $ptitle }}</div>
                    @foreach(array_filter($plines) as $i => $line)
                        <div @if($i === 0) style="font-weight: bold;" @endif>{{ $line }}</div>
                    @endforeach
                </td>
            @endforeach
        </tr>
    </table>
@endif

<table class="lines">
    <thead>
        <tr>
            <th class="c" style="width: 5%;">#</th>
            @foreach($doc['columns'] as [$label, $align, $width])
                <th class="{{ $align === 'r' ? 'r' : ($align === 'c' ? 'c' : '') }}" @if($width) style="width: {{ $width }}%;" @endif>{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($doc['rows'] as $i => $row)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="c muted">{{ $i + 1 }}</td>
                @foreach($row as $c => $cell)
                    @php $align = $doc['columns'][$c][1] ?? 'l'; @endphp
                    <td class="{{ $align === 'r' ? 'r' : ($align === 'c' ? 'c' : '') }}">{{ $cell }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($doc['columns']) + 1 }}" class="c muted">{{ __('No items.') }}</td></tr>
        @endforelse
    </tbody>
</table>

@if(! empty($doc['totals']))
    <table class="plain" style="margin-top: 3mm;">
        <tr>
            <td style="width: 55%;"></td>
            <td>
                <table class="totals">
                    @foreach($doc['totals'] as $t)
                        <tr class="{{ ! empty($t[2]) ? 'grand' : '' }}"><td>{{ $t[0] }}</td><td class="r">{{ $t[1] }}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>
@endif

@if(isset($doc['words']) && $doc['words'] > 0)
    <p style="margin-top: 3mm;">{{ __('In words') }}: {{ \App\Services\PdfService::amountInWords((float) $doc['words']) }}</p>
@endif

@if(! empty($doc['note']))
    <p style="margin-top: 3mm;"><strong>{{ __('Note') }}:</strong> {{ $doc['note'] }}</p>
@endif

@include('pdfs.partials.signatures', ['labels' => $doc['signatures'] ?? [__('Prepared by'), __('Checked by'), __('Approved by')]])
@endsection
