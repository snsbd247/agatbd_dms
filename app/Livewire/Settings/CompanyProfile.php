<?php

namespace App\Livewire\Settings;

use App\Models\Settings\AuditLog;
use App\Models\Settings\CompanyProfile as CompanyProfileModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Company Profile')]
class CompanyProfile extends Component
{
    use WithFileUploads;

    public $logo;

    public string $name = '';
    public string $address = '';
    public string $phone = '';
    public string $email = '';
    public string $invoice_footer = '';
    public int $fiscal_year_start_month = 7;

    public bool $saved = false;

    public function mount(): void
    {
        $profile = CompanyProfileModel::first();

        if ($profile) {
            $this->name = $profile->name;
            $this->address = (string) $profile->address;
            $this->phone = (string) $profile->phone;
            $this->email = (string) $profile->email;
            $this->invoice_footer = (string) $profile->invoice_footer;
        }

        $this->fiscal_year_start_month = \App\Support\FiscalYear::startMonth();
    }

    public function save(): void
    {
        $this->authorize('settings.manage');

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'invoice_footer' => 'nullable|string|max:1000',
        ]);
        $this->validate(['fiscal_year_start_month' => 'required|integer|between:1,12']);
        \App\Models\Settings\AppSetting::put([\App\Support\FiscalYear::SETTING => (string) $this->fiscal_year_start_month]);

        $profile = CompanyProfileModel::first();

        if (! $profile) {
            $profile = CompanyProfileModel::create($data);

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'company_profile.created',
                'auditable_type' => CompanyProfileModel::class,
                'auditable_id' => $profile->id,
                'old_values' => null,
                'new_values' => $data,
                'ip_address' => request()->ip(),
            ]);
        } else {
            $original = $profile->getOriginal();
            $profile->update($data);

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'company_profile.updated',
                'auditable_type' => CompanyProfileModel::class,
                'auditable_id' => $profile->id,
                'old_values' => $original,
                'new_values' => $profile->getChanges(),
                'ip_address' => request()->ip(),
            ]);
        }

        $this->saved = true;
    }

    public function saveLogo(): void
    {
        $this->authorize('settings.manage');

        $this->validate(['logo' => 'required|image|mimes:png,jpg,jpeg|max:1024'], [], ['logo' => 'logo']);

        $profile = CompanyProfileModel::first();

        if (! $profile) {
            $this->addError('logo', 'Save the company name first, then upload the logo.');

            return;
        }

        $old = $profile->logo_path;
        $path = $this->logo->storeAs('company', 'logo-'.now()->format('YmdHis').'.'.$this->logo->extension(), 'public');
        $profile->update(['logo_path' => $path]);

        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'company_profile.logo_updated',
            'auditable_type' => CompanyProfileModel::class,
            'auditable_id' => $profile->id,
            'old_values' => ['logo_path' => $old],
            'new_values' => ['logo_path' => $path],
            'ip_address' => request()->ip(),
        ]);

        $this->reset('logo');
        $this->saved = true;
    }

    public function removeLogo(): void
    {
        $this->authorize('settings.manage');

        $profile = CompanyProfileModel::first();

        if ($profile?->logo_path) {
            Storage::disk('public')->delete($profile->logo_path);
            $profile->update(['logo_path' => null]);
        }
    }

    public function render()
    {
        return view('livewire.settings.company-profile', [
            'logoUrl' => CompanyProfileModel::first()?->logoUrl(),
        ]);
    }
}
