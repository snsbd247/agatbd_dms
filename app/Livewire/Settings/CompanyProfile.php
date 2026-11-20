<?php

namespace App\Livewire\Settings;

use App\Models\Settings\AuditLog;
use App\Models\Settings\CompanyProfile as CompanyProfileModel;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Company Profile')]
class CompanyProfile extends Component
{
    public string $name = '';
    public string $address = '';
    public string $phone = '';
    public string $email = '';
    public string $invoice_footer = '';

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

    public function render()
    {
        return view('livewire.settings.company-profile');
    }
}
