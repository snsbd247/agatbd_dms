@extends('pdfs.layout')

@section('docTitle', __('Payslip').' '.$run->period)

@section('body')
@foreach($slips as $s)
    @php $e = $s->employee; $m = fn ($v) => number_format((float) $v, 2); @endphp
    @if(! $loop->first)<div style="page-break-before: always;"></div>@endif

    @include('pdfs.partials.head', [
        'title' => __('Payslip'),
        'meta' => [$run->periodLabel(), $run->number],
    ])

    <table class="plain" style="margin-bottom: 4mm;">
        <tr>
            <td style="width: 50%;"><strong>{{ $e->name }}</strong> ({{ $e->employee_code }})<br>
                {{ collect([$e->designation ? __($e->designation->name) : null, $e->department ? __($e->department->name) : null])->filter()->implode(' · ') }}</td>
            <td style="width: 50%; text-align: right;">
                {{ __('Working days') }}: {{ (float) $s->working_days }} · {{ __('Present') }}: {{ (float) $s->present_days }}<br>
                {{ __('Paid leave') }}: {{ (float) $s->paid_leave_days }} · {{ __('Unpaid leave') }}: {{ (float) $s->unpaid_leave_days }} · {{ __('Absent') }}: {{ (float) $s->absent_days }}
            </td>
        </tr>
    </table>

    <table class="plain">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 3mm;">
                <table class="lines">
                    <thead><tr><th>{{ __('Earnings') }}</th><th class="r">{{ $currency }}</th></tr></thead>
                    <tbody>
                        <tr><td>{{ __('Basic') }}</td><td class="r">{{ $m($s->basic) }}</td></tr>
                        <tr><td>{{ __('House rent') }}</td><td class="r">{{ $m($s->house_rent) }}</td></tr>
                        <tr><td>{{ __('Medical') }}</td><td class="r">{{ $m($s->medical) }}</td></tr>
                        <tr><td>{{ __('Conveyance') }}</td><td class="r">{{ $m($s->conveyance) }}</td></tr>
                        @if((float) $s->other_allowance)<tr><td>{{ __('Other allowance') }}</td><td class="r">{{ $m($s->other_allowance) }}</td></tr>@endif
                        @if((float) $s->overtime_amount)<tr><td>{{ __('Overtime') }} ({{ (float) $s->overtime_hours }} {{ __('hours') }})</td><td class="r">{{ $m($s->overtime_amount) }}</td></tr>@endif
                        @if((float) $s->commission)<tr><td>{{ __('Commission') }}</td><td class="r">{{ $m($s->commission) }}</td></tr>@endif
                        @if((float) $s->bonus)<tr><td>{{ __('Bonus') }}</td><td class="r">{{ $m($s->bonus) }}</td></tr>@endif
                    </tbody>
                    <tfoot><tr><td><strong>{{ __('Gross') }}</strong></td><td class="r"><strong>{{ $m($s->gross) }}</strong></td></tr></tfoot>
                </table>
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 3mm;">
                <table class="lines">
                    <thead><tr><th>{{ __('Deductions') }}</th><th class="r">{{ $currency }}</th></tr></thead>
                    <tbody>
                        <tr><td>{{ __('Absence / unpaid leave') }}</td><td class="r">{{ $m($s->absent_deduction) }}</td></tr>
                        <tr><td>{{ __('Advance') }}</td><td class="r">{{ $m($s->advance_deduction) }}</td></tr>
                        <tr><td>{{ __('Loan installment') }}</td><td class="r">{{ $m($s->loan_deduction) }}</td></tr>
                        <tr><td>{{ __('Other') }}</td><td class="r">{{ $m($s->other_deduction) }}</td></tr>
                    </tbody>
                    <tfoot><tr><td><strong>{{ __('Total deductions') }}</strong></td><td class="r"><strong>{{ $m($s->deductions()) }}</strong></td></tr></tfoot>
                </table>
            </td>
        </tr>
    </table>

    <table class="totals" style="width: 60%; margin-left: auto; margin-top: 4mm;">
        <tr class="grand"><td>{{ __('Net pay') }}</td><td class="r">{{ $currency }} {{ $m($s->net) }}</td></tr>
    </table>
    <p style="margin-top: 2mm;">{{ __('In words') }}: {{ \App\Services\PdfService::amountInWords((float) $s->net) }}</p>
    @if($s->note)<p class="muted">{{ $s->note }}</p>@endif

    @include('pdfs.partials.signatures', ['labels' => [__('Employee'), __('Prepared by'), __('Authorised signature')]])
@endforeach
@endsection
