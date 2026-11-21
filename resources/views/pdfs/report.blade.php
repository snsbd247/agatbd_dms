@extends('pdfs.layout')

@section('docTitle', $title)

@section('body')
@php
    // A column shows decimals if any of its values has them (money); otherwise whole numbers (counts).
    $decimals = [];
    foreach ($headers as $i => $h) {
        $decimals[$i] = collect($rows)->contains(fn ($r) => str_contains((string) ($r[$i] ?? ''), '.'));
    }
    $num = fn ($v, $i) => number_format((float) $v, $decimals[$i] ? 2 : 0);
    $cell = function ($v, $i) use ($numeric, $num) {
        if ($v === '' || $v === null) {
            return '—';
        }
        if ($numeric[$i] ?? false) {
            return $num($v, $i);
        }
        // Status words such as "paid" or "credit_hold" are translated; names and numbers stay as they are.
        if (preg_match('/^[a-z][a-z_ ]{1,20}$/', $v)) {
            return __(ucfirst(str_replace('_', ' ', $v)));
        }
        if (mb_strlen($v) <= 30 && __($v) !== $v) {
            return __($v);
        }

        return $v;
    };
@endphp

@include('pdfs.partials.head', [
    'title' => $title,
    'meta' => array_filter([
        $period ?: null,
        __('Printed').': '.now()->format('d M Y H:i'),
        __('Rows').': '.count($rows),
    ]),
])

<table class="lines">
    <thead>
        <tr>
            <th class="c" style="width: 4%;">#</th>
            @foreach($headers as $i => $h)
                <th class="{{ ($numeric[$i] ?? false) ? 'r' : '' }}">{{ __($h) }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $n => $row)
            <tr class="{{ $n % 2 ? 'alt' : '' }}">
                <td class="c muted">{{ $n + 1 }}</td>
                @foreach($row as $i => $value)
                    <td class="{{ ($numeric[$i] ?? false) ? 'r' : '' }}">{{ $cell($value, $i) }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($headers) + 1 }}" class="c muted">{{ __('No data for the selected filters.') }}</td></tr>
        @endforelse
    </tbody>
    @if($totals)
        <tfoot>
            <tr>
                <td></td>
                @foreach($headers as $i => $h)
                    @if(isset($totals[$i]))
                        <td class="r">{{ $num($totals[$i], $i) }}</td>
                    @else
                        <td>{{ $loop->first ? __('Total') : '' }}</td>
                    @endif
                @endforeach
            </tr>
        </tfoot>
    @endif
</table>
@endsection
