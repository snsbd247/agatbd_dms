<?php

namespace App\Livewire\Import;

use App\Models\Company;
use App\Models\Import\ImportShipment;
use App\Models\Warehouse;
use App\Services\NumberGeneratorService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Import shipments: from the proforma to goods received at landed cost. */
#[Layout('layouts.app')]
#[Title('Import Shipments')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public bool $showModal = false;
    public ?int $company_id = null;
    public ?int $warehouse_id = null;
    public string $proforma_no = '';
    public string $proforma_date = '';
    public string $currency = 'USD';
    public string $exchange_rate = '';
    public string $allocation_basis = 'value';

    public function mount(): void
    {
        $this->authorize('import.view');
    }

    public function create(): void
    {
        $this->authorize('import.manage');
        $this->reset(['company_id', 'proforma_no', 'exchange_rate']);
        $this->proforma_date = today()->toDateString();
        $this->currency = 'USD';
        $this->allocation_basis = 'value';
        $this->warehouse_id = Warehouse::whereNull('dealer_id')->where('active', true)->orderByRaw("CASE purpose WHEN 'raw_material' THEN 0 ELSE 1 END")->value('id');
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function updatedCompanyId(): void
    {
        $c = $this->company_id ? Company::find($this->company_id) : null;
        if ($c && $c->currency && $c->currency !== 'BDT') {
            $this->currency = $c->currency;
        }
    }

    public function save(NumberGeneratorService $numbers)
    {
        $this->authorize('import.manage');
        $data = $this->validate([
            'company_id' => 'required|exists:companies,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'proforma_no' => 'nullable|string|max:100',
            'proforma_date' => 'nullable|date',
            'currency' => 'required|string|size:3',
            'exchange_rate' => 'required|numeric|gt:0',
            'allocation_basis' => 'required|in:'.implode(',', array_keys(ImportShipment::BASES)),
        ], [], ['company_id' => __('supplier'), 'exchange_rate' => __('exchange rate')]);

        $s = ImportShipment::create($data + [
            'number' => $numbers->generate('import_shipment'),
            'currency' => strtoupper($data['currency']),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return $this->redirectRoute('import.show', $s);
    }

    public function render()
    {
        return view('livewire.import.index', [
            'shipments' => ImportShipment::with(['company', 'items', 'costs'])
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->latest('id')->paginate(20),
            'companies' => Company::where('active', true)->orderByRaw("CASE WHEN currency = 'BDT' THEN 1 ELSE 0 END")->orderBy('name')->get(),
            'warehouses' => Warehouse::whereNull('dealer_id')->where('active', true)->whereIn('purpose', ['general', 'raw_material', 'finished_goods'])->orderBy('name')->get(),
            'inTransit' => ImportShipment::whereIn('status', ['lc_opened', 'shipped', 'arrived'])->count(),
        ]);
    }
}
