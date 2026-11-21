<?php

namespace App\Http\Controllers;

use App\Models\Hr\FinalSettlement;
use App\Models\Hr\PayrollRun;
use App\Models\Hr\Payslip;
use App\Models\Settings\CompanyProfile;
use App\Services\PdfService;

/** Payslips, the salary sheet and the final settlement as PDFs. */
class HrPdfController extends Controller
{
    public function payslip(Payslip $payslip)
    {
        $payslip->load(['run', 'employee.department', 'employee.designation']);

        return PdfService::stream('pdfs.payslips', [
            'run' => $payslip->run,
            'slips' => collect([$payslip]),
            'company' => CompanyProfile::first(),
        ], 'payslip-'.$payslip->employee->employee_code.'-'.$payslip->run->period.'.pdf');
    }

    public function payslips(PayrollRun $run)
    {
        $slips = $run->payslips()->with(['employee.department', 'employee.designation'])->get()->sortBy(fn ($s) => $s->employee->employee_code)->values();

        return PdfService::stream('pdfs.payslips', [
            'run' => $run,
            'slips' => $slips,
            'company' => CompanyProfile::first(),
        ], 'payslips-'.$run->period.'.pdf');
    }

    public function sheet(PayrollRun $run)
    {
        $slips = $run->payslips()->with('employee')->get()->sortBy(fn ($s) => $s->employee->employee_code)->values();

        return PdfService::stream('pdfs.salary-sheet', [
            'run' => $run,
            'slips' => $slips,
            'company' => CompanyProfile::first(),
        ], 'salary-sheet-'.$run->period.'.pdf', 'landscape');
    }

    public function settlement(FinalSettlement $settlement)
    {
        $settlement->load(['employee.department', 'employee.designation']);

        return PdfService::stream('pdfs.final-settlement', [
            's' => $settlement,
            'company' => CompanyProfile::first(),
        ], 'final-settlement-'.$settlement->employee->employee_code.'.pdf');
    }
}
