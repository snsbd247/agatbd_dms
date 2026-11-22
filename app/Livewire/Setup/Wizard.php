<?php

namespace App\Livewire\Setup;

use App\Models\Factory;
use App\Models\Product;
use App\Models\Settings\AppSetting;
use App\Models\Settings\CompanyProfile;
use App\Models\Warehouse;
use App\Services\Vat\VatService;
use App\Support\FiscalYear;
use App\Support\Modules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * First-run setup for a new install: business type (loads the modules),
 * company, fiscal year and VAT, and the first warehouses. Shown to an admin
 * once, on an empty system; it can be skipped and everything changed later
 * in Settings.
 */
#[Layout('layouts.app')]
#[Title('Setup')]
class Wizard extends Component
{
    public const DONE = 'setup_done';

    public int $step = 1;
    public string $type = '';
    public string $name = '';
    public string $address = '';
    public string $phone = '';
    public string $email = '';
    public int $fiscal_start = 7;
    public bool $vat = false;
    public string $bin = '';
    public string $warehouse = 'Main Warehouse';
    public string $factory = 'Factory';

    public static function needed(): bool
    {
        return ! AppSetting::flag(self::DONE) && Modules::type() === null
            && Product::count() === 0 && Warehouse::count() === 0;
    }

    public function mount(): void
    {
        $this->authorize('settings.manage');
        $p = CompanyProfile::first();
        $this->type = Modules::type() ?? '';
        $this->name = (string) $p?->name;
        $this->address = (string) $p?->address;
        $this->phone = (string) $p?->phone;
        $this->email = (string) $p?->email;
        $this->fiscal_start = FiscalYear::startMonth();
        $this->bin = (string) VatService::bin();
    }

    public function next(): void
    {
        $this->authorize('settings.manage');
        match ($this->step) {
            1 => $this->validate(['type' => 'required|in:'.implode(',', array_keys(Modules::TYPES))], ['type.required' => __('Choose the type of business.')]),
            2 => $this->validate(['name' => 'required|string|max:255', 'address' => 'nullable|string|max:255', 'phone' => 'nullable|string|max:50', 'email' => 'nullable|email|max:255']),
            3 => $this->validate(['fiscal_start' => 'required|integer|between:1,12', 'bin' => [$this->vat ? 'required' : 'nullable', 'string', 'max:20']], [], ['bin' => __('BIN')]),
            default => null,
        };
        $this->step = min(4, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function finish()
    {
        $this->authorize('settings.manage');
        $this->validate([
            'type' => 'required|in:'.implode(',', array_keys(Modules::TYPES)),
            'name' => 'required|string|max:255',
            'warehouse' => 'required|string|max:255',
            'factory' => [$this->type === 'trading' ? 'nullable' : 'required', 'string', 'max:255'],
        ]);

        Modules::applyTemplate($this->type);
        CompanyProfile::updateOrCreate(['id' => CompanyProfile::value('id')], [
            'name' => $this->name, 'address' => $this->address ?: null, 'phone' => $this->phone ?: null, 'email' => $this->email ?: null,
        ]);
        AppSetting::put([
            FiscalYear::SETTING => (string) $this->fiscal_start,
            VatService::ENABLED => $this->vat,
            VatService::BIN => trim($this->bin) ?: null,
            self::DONE => true,
        ]);

        Warehouse::firstOrCreate(['name' => $this->warehouse], ['type' => 'central', 'purpose' => 'general', 'active' => true]);
        Warehouse::firstOrCreate(['name' => __('Damage store')], ['type' => 'central', 'purpose' => 'damage', 'active' => true]);
        if ($this->type !== 'trading') {
            $f = Factory::firstOrCreate(['code' => 'F1'], ['name' => $this->factory, 'active' => true]);
            foreach (['raw_material' => 'raw material store', 'finished_goods' => 'finished goods store', 'damage' => 'damage store'] as $purpose => $label) {
                Warehouse::firstOrCreate(['name' => $this->factory.' '.__($label)], ['type' => 'central', 'purpose' => $purpose, 'factory_id' => $f->id, 'active' => true]);
            }
        }

        session()->flash('status', __('Setup complete. You can change all of this later in Settings.'));

        return $this->redirectRoute('dashboard');
    }

    public function skip()
    {
        $this->authorize('settings.manage');
        AppSetting::put([self::DONE => true]);

        return $this->redirectRoute('dashboard');
    }

    public function render()
    {
        return view('livewire.setup.wizard');
    }
}
