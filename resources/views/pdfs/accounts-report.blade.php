@extends('pdfs.layout')

@section('docTitle', $title)

@section('body')
@include('pdfs.partials.head', [
    'title' => $title,
    'meta' => array_filter([
        $subtitle ?: null,
        $period,
        __('Printed').': '.now()->format('d M Y H:i'),
    ]),
])

<style>
    table.lines tr.grp td { font-weight: bold; background: #f5f5f5; }
    table.lines tr.tot td { font-weight: bold; border-top: 1.5px solid #222; }
</style>

@include('accounts.tables.'.$table, ['data' => $data, 'links' => false, 'partyFixed' => $partyFixed])
@endsection
