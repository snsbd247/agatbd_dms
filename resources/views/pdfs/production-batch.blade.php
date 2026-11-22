@extends('pdfs.layout')

@section('docTitle', __('Batch record').' '.$order->batch_no)

@section('body')
@php
    $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.');
    $u = $order->product?->unit?->short_code;
@endphp
@include('pdfs.partials.head', [
    'title' => __('Batch record'),
    'meta' => [$order->number, __('Batch').': '.$order->batch_no, $order->statusLabel()],
])

<table class="plain" style="margin-bottom: 3mm;">
    <tr>
        <td><strong>{{ __('Product') }}:</strong> {{ $order->product?->name }} ({{ $order->product?->sku }})</td>
        <td><strong>{{ __('Formula') }}:</strong> {{ $order->bom?->label() }}</td>
    </tr>
    <tr>
        <td><strong>{{ __('Factory') }}:</strong> {{ $order->factory?->name }}@if($order->machine) · {{ $order->machine->name }}@endif</td>
        <td><strong>{{ __('Planned') }}:</strong> {{ $q($order->planned_quantity) }} {{ $u }}</td>
    </tr>
    <tr>
        <td><strong>{{ __('Mfg date') }}:</strong> {{ $order->mfg_date?->format('d M Y') }}</td>
        <td><strong>{{ __('Expiry date') }}:</strong> {{ $order->expiry_date?->format('d M Y') ?? '—' }}</td>
    </tr>
</table>

<h3>{{ __('Materials') }}</h3>
<table class="lines">
    <thead><tr><th>{{ __('Material') }}</th><th class="r">{{ __('Required') }}</th><th class="r">{{ __('Issued') }}</th></tr></thead>
    <tbody>
        @foreach($materials as $m)
            <tr><td>{{ $m['product']->name }}</td><td class="r">{{ $q($m['required']) }} {{ $m['product']->unit?->short_code }}</td><td class="r">{{ $q($m['issued']) }}</td></tr>
        @endforeach
    </tbody>
</table>

@if($order->issues->isNotEmpty())
    <h3>{{ __('Material batches used') }}</h3>
    <table class="lines">
        <thead><tr><th>{{ __('Material') }}</th><th>{{ __('Batch') }}</th><th class="r">{{ __('Quantity') }}</th><th class="r">{{ __('Unit cost') }}</th><th class="r">{{ __('Value') }}</th></tr></thead>
        <tbody>
            @foreach($order->issues->sortBy('id') as $i)
                <tr><td>{{ $i->product?->name }}</td><td>{{ $i->batch?->batch_no }}</td><td class="r">{{ $q($i->quantity) }}</td><td class="r">{{ number_format((float) $i->unit_cost, 2) }}</td><td class="r">{{ number_format((float) $i->quantity * (float) $i->unit_cost, 2) }}</td></tr>
            @endforeach
        </tbody>
    </table>
@endif

<h3>{{ __('Quality checks') }}</h3>
@forelse($order->qcChecks as $c)
    <p>{{ __(ucfirst($c->result)) }} — {{ $c->checked_at?->format('d M Y H:i') }} — {{ $c->checker?->name }}@if($c->parameters)<br>{{ $c->parameters }}@endif @if($c->notes)<br><span class="muted">{{ $c->notes }}</span>@endif</p>
@empty
    <p class="muted">—</p>
@endforelse

@if($order->status === 'completed')
    <h3>{{ __('Result') }}</h3>
    <table class="lines">
        <tbody>
            <tr><td>{{ __('Good output') }}</td><td class="r">{{ $q($order->good_quantity) }} {{ $u }} ({{ $order->yieldPercent() }}%)</td></tr>
            <tr><td>{{ __('Rejected') }}</td><td class="r">{{ $q($order->rejected_quantity) }} {{ $u }}</td></tr>
            <tr><td>{{ __('Material cost') }}</td><td class="r">{{ number_format((float) $order->material_cost, 2) }}</td></tr>
            <tr><td>{{ __('Labour cost') }}</td><td class="r">{{ number_format((float) $order->labour_cost, 2) }}</td></tr>
            <tr><td>{{ __('Overhead cost') }}</td><td class="r">{{ number_format((float) $order->overhead_cost, 2) }}</td></tr>
            <tr><td><strong>{{ __('Total cost') }}</strong></td><td class="r"><strong>{{ number_format((float) $order->total_cost, 2) }}</strong></td></tr>
            <tr><td>{{ __('Cost per unit') }}</td><td class="r">{{ number_format((float) $order->unit_cost, 2) }}</td></tr>
        </tbody>
    </table>
@endif

@include('pdfs.partials.signatures', ['labels' => [__('Production'), __('Quality control'), __('Factory manager')]])
@endsection
