@extends('pdfs.layout')

@section('docTitle', __('Salary sheet').' '.$run->period)

@section('body')
@php
    $m = fn ($v) => (float) $v ? number_format((float) $v, 2) : '';
    $sum = fn ($f) => $slips->sum(fn ($s) => (float) $s->{$f});
@endphp
@include('pdfs.partials.head', [
    'title' => __('Salary sheet'),
    'meta' => [$run->periodLabel(), $run->number, __(ucfirst($run->status))],
])

<table class="lines" style="font-size: 8.5px;">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('Employee') }}</th>
            <th class="r">{{ __('Days') }}</th>
            <th class="r">{{ __('Basic') }}</th>
            <th class="r">{{ __('Allowances') }}</th>
            <th class="r">{{ __('OT') }}</th>
            <th class="r">{{ __('Commission') }}</th>
            <th class="r">{{ __('Bonus') }}</th>
            <th class="r">{{ __('Gross') }}</th>
            <th class="r">{{ __('Absence') }}</th>
            <th class="r">{{ __('Advance') }}</th>
            <th class="r">{{ __('Loan') }}</th>
            <th class="r">{{ __('Other') }}</th>
            <th class="r">{{ __('Net') }}</th>
            <th style="width: 12%;">{{ __('Signature') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($slips as $i => $s)
            <tr>
                <td class="muted">{{ $i + 1 }}</td>
                <td>{{ $s->employee->name }}<br><span class="muted">{{ $s->employee->employee_code }}</span></td>
                <td class="r">{{ (float) $s->present_days }}/{{ (float) $s->working_days }}</td>
                <td class="r">{{ $m($s->basic) }}</td>
                <td class="r">{{ $m($s->house_rent + $s->medical + $s->conveyance + $s->other_allowance) }}</td>
                <td class="r">{{ $m($s->overtime_amount) }}</td>
                <td class="r">{{ $m($s->commission) }}</td>
                <td class="r">{{ $m($s->bonus) }}</td>
                <td class="r">{{ $m($s->gross) }}</td>
                <td class="r">{{ $m($s->absent_deduction) }}</td>
                <td class="r">{{ $m($s->advance_deduction) }}</td>
                <td class="r">{{ $m($s->loan_deduction) }}</td>
                <td class="r">{{ $m($s->other_deduction) }}</td>
                <td class="r"><strong>{{ number_format((float) $s->net, 2) }}</strong></td>
                <td></td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td></td>
            <td><strong>{{ __('Total') }}</strong></td>
            <td></td>
            <td class="r">{{ $m($sum('basic')) }}</td>
            <td class="r">{{ $m($sum('house_rent') + $sum('medical') + $sum('conveyance') + $sum('other_allowance')) }}</td>
            <td class="r">{{ $m($sum('overtime_amount')) }}</td>
            <td class="r">{{ $m($sum('commission')) }}</td>
            <td class="r">{{ $m($sum('bonus')) }}</td>
            <td class="r">{{ $m($sum('gross')) }}</td>
            <td class="r">{{ $m($sum('absent_deduction')) }}</td>
            <td class="r">{{ $m($sum('advance_deduction')) }}</td>
            <td class="r">{{ $m($sum('loan_deduction')) }}</td>
            <td class="r">{{ $m($sum('other_deduction')) }}</td>
            <td class="r"><strong>{{ number_format($sum('net'), 2) }}</strong></td>
            <td></td>
        </tr>
    </tfoot>
</table>

<p style="margin-top: 3mm;">{{ __('Net payable') }}: {{ $currency }} {{ number_format($sum('net'), 2) }} — {{ \App\Services\PdfService::amountInWords($sum('net')) }}</p>

@include('pdfs.partials.signatures', ['labels' => [__('Prepared by'), __('Checked by'), __('Approved by')]])
@endsection
