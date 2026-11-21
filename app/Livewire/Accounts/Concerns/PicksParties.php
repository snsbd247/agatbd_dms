<?php

namespace App\Livewire\Accounts\Concerns;

use App\Models\Accounts\Account;
use App\Models\Company;
use App\Models\Representative;
use App\Models\Retailer;

/** Which party a line on an account needs, and the choices for it. */
trait PicksParties
{
    /** Party type an account's lines must name (null = none). */
    protected function partyTypeFor(?Account $account): ?string
    {
        return match ($account?->system_key) {
            'ar' => 'retailer',
            'ap' => auth()->user()->dealer_id ? 'head_office' : 'company',
            'employee_advance' => 'representative',
            default => null,
        };
    }

    /** @return array<string, array<int|string, string>> options per party type */
    protected function partyOptions(): array
    {
        return [
            'retailer' => Retailer::orderBy('name')->pluck('name', 'id')->all(),
            'company' => Company::orderBy('name')->pluck('name', 'id')->all(),
            'representative' => Representative::orderBy('name')->pluck('name', 'id')->all(),
            'head_office' => ['' => __('Agro Tech (head office)')],
        ];
    }
}
