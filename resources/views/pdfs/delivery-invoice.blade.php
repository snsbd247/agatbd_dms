@extends('pdfs.layout')

@section('docTitle', __('Delivery Invoice').' - '.$invoice->number)

@section('body')
    @include('pdfs.partials.delivery-invoice-body', ['invoice' => $invoice])
@endsection
