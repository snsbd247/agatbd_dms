@extends('pdfs.layout')

@section('docTitle', __('Sales Invoice').' - '.$invoice->number)

@section('body')
@php $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.') ?: '0'; @endphp

@include('pdfs.partials.head', [
    'title' => __('Sales Invoice'),
    'meta' => [
        '#'.$invoice->number,
        __('Date').': '.$invoice->invoice_date->format('d M Y'),
    ],
])

<table class="plain" style="margin-bottom: 4mm;">
    <tr>
        <td style="width: 50%; padding-right: 4mm;">
            <div class="box-title">{{ __('Bill To') }}</div>
            <strong>{{ $invoice->retailer->name ?? '—' }}</strong><br>
            @if($invoice->retailer?->code)<span class="muted">{{ $invoice->retailer->code }}</span><br>@endif
            @if($invoice->retailer?->address){{ $invoice->retailer->address }}<br>@endif
            @if($invoice->retailer?->phone){{ $invoice->retailer->phone }}@endif
        </td>
        <td style="width: 50%;">
            <div class="box-title">{{ __('Invoice Details') }}</div>
            {{ __('Sales Order') }}: <strong>{{ $invoice->salesOrder->number ?? '—' }}</strong><br>
            @if($invoice->salesOrder?->representative){{ __('Representative') }}: {{ $invoice->salesOrder->representative->name }}<br>@endif
            {{ __('Payment') }}: <span class="badge">{{ __(ucfirst($invoice->status)) }}</span>
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th class="c" style="width: 6%;">#</th>
            <th style="width: 46%;">{{ __('Product') }}</th>
            <th class="r" style="width: 14%;">{{ __('Quantity') }}</th>
            <th class="r" style="width: 16%;">{{ __('Unit Price') }}</th>
            <th class="r" style="width: 18%;">{{ __('Total') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($invoice->items as $i => $item)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="c">{{ $i + 1 }}</td>
                <td>
                    <strong>{{ $item->product->name ?? '—' }}</strong>
                    @if($item->batch)<br><span class="small muted">{{ __('Batch') }}: {{ $item->batch->batch_no }}</span>@endif
                </td>
                <td class="r">{{ $qty($item->quantity) }} {{ $item->product?->unit?->short_code }}</td>
                <td class="r">{{ number_format($item->unit_price, 2) }}</td>
                <td class="r"><strong>{{ number_format($item->line_total, 2) }}</strong></td>
            </tr>
        @empty
            <tr><td colspan="5" class="c muted">{{ __('No items') }}</td></tr>
        @endforelse
    </tbody>
</table>

<table class="plain" style="margin-top: 3mm;">
    <tr>
        <td style="width: 55%; padding-right: 6mm;">
            <p class="small muted" style="margin: 0;">{{ __('In words') }}: {{ \App\Services\PdfService::amountInWords((float) $invoice->grand_total) }}</p>
        </td>
        <td style="width: 45%;">
            <table class="totals">
                <tr><td>{{ __('Subtotal') }}</td><td class="r">{{ $currency }} {{ number_format($invoice->subtotal, 2) }}</td></tr>
                @if((float) $invoice->discount > 0)
                    <tr><td>{{ __('Discount') }}</td><td class="r">− {{ $currency }} {{ number_format($invoice->discount, 2) }}</td></tr>
                @endif
                <tr class="grand"><td>{{ __('Grand Total') }}</td><td class="r">{{ $currency }} {{ number_format($invoice->grand_total, 2) }}</td></tr>
                @if((float) $invoice->vat_amount > 0)
                    <tr><td class="small muted">{{ __('Includes VAT') }}</td><td class="r small muted">{{ $currency }} {{ number_format($invoice->vat_amount, 2) }}</td></tr>
                @endif
                <tr><td>{{ __('Paid') }}</td><td class="r">{{ $currency }} {{ number_format($invoice->paid_amount, 2) }}</td></tr>
                <tr><td><strong>{{ __('Due') }}</strong></td><td class="r"><strong>{{ $currency }} {{ number_format($invoice->due_amount, 2) }}</strong></td></tr>
            </table>
        </td>
    </tr>
</table>

@include('pdfs.partials.signatures', ['labels' => [__('Received by'), __('Authorised signature')]])

<div class="foot">
    @include('pdfs.partials.footer-note')
    {{ __('Computer-generated document.') }} {{ __('Printed') }}: {{ now()->format('d M Y H:i') }}
</div>
@endsection
