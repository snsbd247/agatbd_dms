@extends('pdfs.layout')

@section('docTitle', __('Delivery Invoices'))

@section('body')
    @foreach($invoices as $invoice)
        @include('pdfs.partials.delivery-invoice-body', ['invoice' => $invoice])
        @unless($loop->last)
            <div class="page-break"></div>
        @endunless
    @endforeach
@endsection
