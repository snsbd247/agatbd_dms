<?php

namespace App\Livewire\Settings;

use App\Services\DataImportService;
use App\Support\SpreadsheetReader;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Upload products, customers, suppliers and opening stock from a CSV or
 * Excel file. Step 1 checks the file and lists every problem by line;
 * step 2 saves it.
 */
#[Layout('layouts.app')]
#[Title('Data Import')]
class DataImport extends Component
{
    use WithFileUploads;

    public string $type = 'products';

    public $file;

    public string $opening_date = '';

    /** @var array<int, array<string, string>> */
    public array $rows = [];

    /** @var array<int, array<int, string>> */
    public array $errorsByLine = [];

    /** @var array<int, string> */
    public array $missing = [];

    public ?array $result = null;

    public function mount(): void
    {
        $this->authorize('settings.manage');
        $this->opening_date = app(\App\Services\Accounts\OpeningBalanceService::class)->current()['date'];
    }

    public function updatedType(): void
    {
        $this->resetChecked();
        $this->file = null;
    }

    public function updatedFile(): void
    {
        $this->resetChecked();
    }

    private function resetChecked(): void
    {
        $this->reset(['rows', 'errorsByLine', 'missing', 'result']);
    }

    public function check(DataImportService $service): void
    {
        $this->authorize('settings.manage');
        $this->validate([
            'type' => 'required|in:'.implode(',', array_keys(DataImportService::TYPES)),
            'file' => 'required|file|max:10240|mimes:csv,txt,xlsx',
        ]);
        $this->resetChecked();

        try {
            $raw = SpreadsheetReader::read($this->file->getRealPath(), $this->file->getClientOriginalExtension());
        } catch (\RuntimeException $e) {
            $this->addError('file', $e->getMessage());

            return;
        }

        if (count($raw) > 5000) {
            $this->addError('file', __('At most 5,000 rows at a time. Split the file.'));

            return;
        }

        $checked = $service->check($this->type, $raw);
        $this->rows = $checked['rows'];
        $this->errorsByLine = $checked['errors'];
        $this->missing = $checked['missing'];
    }

    public function import(DataImportService $service): void
    {
        $this->authorize('settings.manage');
        if (! $this->rows || $this->errorsByLine) {
            return;
        }
        if (in_array($this->type, ['customers', 'suppliers', 'opening_stock'], true)) {
            $this->validate(['opening_date' => 'required|date']);
        }

        try {
            $this->result = $service->import($this->type, $this->rows, $this->opening_date);
        } catch (\RuntimeException $e) {
            $this->addError('file', $e->getMessage());

            return;
        }

        $this->rows = [];
        $this->file = null;
    }

    public function render()
    {
        return view('livewire.settings.data-import', [
            'types' => DataImportService::LABELS,
            'columns' => DataImportService::TYPES[$this->type],
            'preview' => array_slice($this->rows, 0, 20, true),
        ]);
    }
}
