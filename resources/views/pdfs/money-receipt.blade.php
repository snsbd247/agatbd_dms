@extends('pdfs.layout')

@section('docTitle', __('Money Receipt').' - '.$collection->number)

@section('body')
@php
    $methods = ['cash' => __('Cash'), 'bank' => __('Bank'), 'mobile' => __('Mobile')];
@endphp

{{-- Two copies on one A4 page: one for the retailer, one for the office. --}}
@foreach([__('Retailer copy'), __('Office copy')] as $copy)
    <div style="{{ $loop->first ? 'padding-bottom: 8mm; border-bottom: 1px dashed #999; margin-bottom: 8mm;' : '' }}">
        @include('pdfs.partials.head', [
            'title' => __('Money Receipt'),
            'meta' => [
                '#'.$collection->number,
                __('Date').': '.$collection->collection_date->format('d M Y'),
                $copy,
            ],
        ])

        <table class="lines" style="margin-top: 2mm;">
            <tr>
                <td style="width: 32%; background: #f6f6f6;"><strong>{{ __('Received from') }}</strong></td>
                <td>
                    <strong>{{ $collection->retailer->name ?? '—' }}</strong>
                    @if($collection->retailer?->code) <span class="muted">({{ $collection->retailer->code }})</span>@endif
                    @if($collection->retailer?->address)<br><span class="small">{{ $collection->retailer->address }}</span>@endif
                </td>
            </tr>
            <tr>
                <td style="background: #f6f6f6;"><strong>{{ __('Amount') }}</strong></td>
                <td style="font-size: 14px;"><strong>{{ $currency }} {{ number_format($collection->amount, 2) }}</strong></td>
            </tr>
            <tr>
                <td style="background: #f6f6f6;"><strong>{{ __('In words') }}</strong></td>
                <td>{{ \App\Services\PdfService::amountInWords((float) $collection->amount) }}</td>
            </tr>
            <tr>
                <td style="background: #f6f6f6;"><strong>{{ __('Payment method') }}</strong></td>
                <td>
                    {{ $methods[$collection->method] ?? __(ucfirst(str_replace('_', ' ', $collection->method))) }}
                    @if($collection->reference_no) — {{ __('Ref') }}: {{ $collection->reference_no }}@endif
                </td>
            </tr>
            @if($balanceAfter !== null)
                <tr>
                    <td style="background: #f6f6f6;"><strong>{{ __('Due after this payment') }}</strong></td>
                    <td>{{ $currency }} {{ number_format($balanceAfter, 2) }}</td>
                </tr>
            @endif
        </table>

        @include('pdfs.partials.signatures', ['labels' => [__('Paid by'), __('Received by').($collection->collectedBy ? ': '.$collection->collectedBy->name : '')]])
    </div>
@endforeach

<div class="foot">
    {{ __('Computer-generated document.') }} {{ __('Printed') }}: {{ now()->format('d M Y H:i') }}
</div>
@endsection
