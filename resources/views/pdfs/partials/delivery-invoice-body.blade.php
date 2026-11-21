@php $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.') ?: '0'; @endphp

@include('pdfs.partials.head', [
    'title' => __('Delivery Invoice'),
    'meta' => [
        '#'.$invoice->number,
        __('Date').': '.$invoice->invoice_date->format('d M Y'),
    ],
])

<table class="plain" style="margin-bottom: 4mm;">
    <tr>
        <td style="width: 36%; padding-right: 4mm;">
            <div class="box-title">{{ __('Bill To') }}</div>
            <strong>{{ $invoice->retailer->name ?? '—' }}</strong><br>
            @if($invoice->retailer?->code)<span class="muted">{{ $invoice->retailer->code }}</span><br>@endif
            @if($invoice->retailer?->address){{ $invoice->retailer->address }}<br>@endif
            @if($invoice->retailer?->phone){{ $invoice->retailer->phone }}@endif
        </td>
        <td style="width: 34%; padding-right: 4mm;">
            <div class="box-title">{{ __('Invoice Details') }}</div>
            {{ __('Sales Order') }}: <strong>{{ $invoice->salesOrder->number ?? '—' }}</strong><br>
            {{ __('Delivery Note') }}: <strong>{{ $invoice->deliveryNote->number ?? '—' }}</strong><br>
            {{ __('Status') }}: <span class="badge">{{ __(ucfirst($invoice->status)) }}</span>
        </td>
        <td style="width: 30%;">
            <div class="box-title">{{ __('Warehouse') }}</div>
            <strong>{{ $invoice->salesOrder->warehouse->name ?? '—' }}</strong><br>
            {{ $invoice->salesOrder->warehouse->address ?? '' }}
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th class="c" style="width: 6%;">#</th>
            <th style="width: 46%;">{{ __('Product') }} / {{ __('Batch') }}</th>
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
                <td class="r">{{ $qty($item->quantity) }}</td>
                <td class="r">{{ number_format($item->unit_price, 2) }}</td>
                <td class="r"><strong>{{ number_format($item->total_price, 2) }}</strong></td>
            </tr>
        @empty
            <tr><td colspan="5" class="c muted">{{ __('No items') }}</td></tr>
        @endforelse
    </tbody>
</table>

<table class="plain" style="margin-top: 3mm;">
    <tr>
        <td style="width: 55%;"></td>
        <td style="width: 45%;">
            <table class="totals">
                <tr><td>{{ __('Subtotal') }}</td><td class="r">{{ $currency }} {{ number_format($invoice->subtotal, 2) }}</td></tr>
                @if((float) $invoice->discount > 0)
                    <tr><td>{{ __('Discount') }}</td><td class="r">− {{ $currency }} {{ number_format($invoice->discount, 2) }}</td></tr>
                @endif
                <tr class="grand"><td>{{ __('Grand Total') }}</td><td class="r">{{ $currency }} {{ number_format($invoice->grand_total, 2) }}</td></tr>
            </table>
            <p class="small muted" style="margin: 2mm 0 0;">{{ \App\Services\PdfService::amountInWords((float) $invoice->grand_total) }}</p>
        </td>
    </tr>
</table>

@include('pdfs.partials.signatures', ['labels' => [__('Received by'), __('Authorised signature')]])

<div class="foot">
    @include('pdfs.partials.footer-note')
    {{ __('Computer-generated document.') }} {{ __('Printed') }}: {{ now()->format('d M Y H:i') }}
</div>
