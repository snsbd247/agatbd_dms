<?php

namespace Tests\Feature;

use App\Models\Cheque;
use App\Models\Settings\AppSetting;
use App\Support\Reminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Dashboard reminders: what is waiting, only for whoever can act on it. */
class RemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
    }

    private function texts($user): string
    {
        return collect(Reminders::for($user))->pluck('text')->implode(' | ');
    }

    public function test_due_cheques_and_setup_gaps_are_shown_to_the_admin(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->actingAs($admin);
        $r = $this->retailer();
        Cheque::create(['number' => 'CHQ-1', 'retailer_id' => $r->id, 'cheque_no' => '1', 'bank_name' => 'B', 'received_date' => today(),
            'cheque_date' => today()->subDay(), 'amount' => 5000, 'status' => 'in_hand']);
        AppSetting::put(['vat_enabled' => '1', 'vat_bin' => '000000000-0000']);

        $t = $this->texts($admin);
        $this->assertStringContainsString('1 cheque(s) due or overdue', $t);
        $this->assertStringContainsString('BIN is missing or still the demo one', $t);
        $this->assertStringContainsString('off-site copy', $t);

        $this->get(route('dashboard.accounts'))->assertOk()->assertSee('cheque(s) due or overdue');
    }

    public function test_unrun_depreciation_and_unsettled_vat_are_reminded(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->actingAs($admin);
        AppSetting::put(['vat_enabled' => '1', 'vat_bin' => '123456789-0101']);
        app(\App\Services\FixedAssetService::class)->add([
            'name' => 'Mixer', 'category' => 'machinery', 'account_id' => \App\Models\Accounts\Account::where('code', '1220')->value('id'),
            'purchase_date' => today()->subMonths(3)->toDateString(), 'cost' => 6000, 'salvage_value' => 0, 'method' => 'straight_line',
            'useful_life_months' => 60, 'rate_percent' => 0, 'funded_by' => 'opening',
        ]);
        app(\App\Services\Accounts\LedgerService::class)->post(['type' => 'auto', 'entry_date' => today()->subMonthNoOverflow()->startOfMonth()->addDays(2)], [
            ['account' => 'cash', 'debit' => 115], ['account' => 'sales', 'credit' => 100], ['account' => 'vat_output', 'credit' => 15],
        ]);

        $t = $this->texts($admin);
        $this->assertStringContainsString('Depreciation has not been run', $t);
        $this->assertStringContainsString('is not settled yet', $t);

        app(\App\Services\FixedAssetService::class)->run(today()->subMonthNoOverflow()->format('Y-m'));
        $this->assertStringNotContainsString('Depreciation has not been run', $this->texts($admin));
    }

    public function test_people_only_see_what_they_can_act_on(): void
    {
        $hr = $this->userWithRole('HR Manager');
        $this->actingAs($hr);
        $r = $this->retailer();
        Cheque::create(['number' => 'CHQ-2', 'retailer_id' => $r->id, 'cheque_no' => '2', 'bank_name' => 'B', 'received_date' => today(),
            'cheque_date' => today(), 'amount' => 100, 'status' => 'in_hand']);

        $this->assertSame('', $this->texts($hr));
    }
}
