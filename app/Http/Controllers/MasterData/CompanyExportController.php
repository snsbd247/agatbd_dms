<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyExportController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = Company::query()
            ->when($request->query('search'), fn ($q) => $q->where('name', 'like', '%'.$request->query('search').'%')
                ->orWhere('phone', 'like', '%'.$request->query('search').'%')
                ->orWhere('email', 'like', '%'.$request->query('search').'%'))
            ->when($request->query('filter_status') !== null && $request->query('filter_status') !== '', fn ($q) => $q->where('active', $request->query('filter_status') === 'active'))
            ->when($request->query('filter_territory'), fn ($q) => $q->where('address', $request->query('filter_territory')))
            ->orderBy('name');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Company Name', 'Code', 'Phone', 'Email', 'Territory', 'Status', 'Joining Date']);
            $query->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $company) {
                    fputcsv($out, [
                        $company->name,
                        $company->code,
                        $company->phone,
                        $company->email,
                        $company->address,
                        $company->active ? 'Active' : 'Inactive',
                        $company->created_at->toDateString(),
                    ]);
                }
            });
            fclose($out);
        }, 'companies-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }
}
