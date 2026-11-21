@extends('pdfs.layout')

@section('docTitle', __('Final settlement'))

@section('body')
@php $e = $s->employee; $m = fn ($v) => number_format((float) $v, 2); @endphp
@include('pdfs.partials.head', [
    'title' => __('Final settlement'),
    'meta' => array_filter([$s->number, __('Date').': '.$s->exit_date->format('d M Y'), $s->status === 'paid' ? __('Paid').' '.$s->paid_at->format('d M Y') : __('Not paid')]),
])

<table class="plain" style="margin-bottom: 4mm;">
    <tr>
        <td><strong>{{ $e->name }}</strong> ({{ $e->employee_code }})<br>
            {{ collect([$e->designation ? __($e->designation->name) : null, $e->department ? __($e->department->name) : null])->filter()->implode(' · ') }}<br>
            {{ __('Joined') }} {{ $e->join_date->format('d M Y') }} · {{ __('Last working day') }} {{ $s->exit_date->format('d M Y') }} · {{ __(ucfirst($s->reason)) }}</td>
    </tr>
</table>

<table class="lines">
    <thead><tr><th>{{ __('Particulars') }}</th><th class="r" style="width: 30%;">{{ $currency }}</th></tr></thead>
    <tbody>
        <tr><td>{{ __('Salary for :n days of the last month', ['n' => (float) $s->salary_days]) }}</td><td class="r">{{ $m($s->salary_amount) }}</td></tr>
        <tr><td>{{ __('Unused earned leave: :n days', ['n' => (float) $s->leave_days]) }}</td><td class="r">{{ $m($s->leave_encashment) }}</td></tr>
        <tr><td>{{ __('Gratuity (:y years of service)', ['y' => (float) $s->service_years]) }}</td><td class="r">{{ $m($s->gratuity) }}</td></tr>
        @if((float) $s->other_payable)<tr><td>{{ __('Other payable') }}</td><td class="r">{{ $m($s->other_payable) }}</td></tr>@endif
        <tr><td>{{ __('Less: advances and loans outstanding') }}</td><td class="r">({{ $m($s->loan_deduction) }})</td></tr>
        @if((float) $s->other_deduction)<tr><td>{{ __('Less: other deduction') }}</td><td class="r">({{ $m($s->other_deduction) }})</td></tr>@endif
    </tbody>
    <tfoot><tr><td><strong>{{ __('Net payable') }}</strong></td><td class="r"><strong>{{ $m($s->net) }}</strong></td></tr></tfoot>
</table>
<p style="margin-top: 2mm;">{{ __('In words') }}: {{ \App\Services\PdfService::amountInWords((float) $s->net) }}</p>
@if($s->note)<p class="muted">{{ $s->note }}</p>@endif

@include('pdfs.partials.signatures', ['labels' => [__('Received by').': '.$e->name, __('Prepared by'), __('Authorised signature')]])
@endsection
