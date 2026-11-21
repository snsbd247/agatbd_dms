<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\User;
use App\Services\DealerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Who may open what. Guards against roles silently getting every permission
 * again (which is what the live database had until 21 Nov 2026).
 */
class AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public static function matrix(): array
    {
        //                     route                      Admin Manager Dealer Rep  Retailer
        return [
            'settings users' => ['settings.users',          200,   403,   403,   403, 403],
            'payroll' => ['payroll.index',                   200,   403,   403,   403, 403],
            'backup' => ['more.backup-restore',              200,   403,   403,   403, 403],
            'email & sms' => ['settings.messaging',          200,   403,   403,   403, 403],
            'audit log' => ['settings.audit-log',            200,   403,   403,   403, 403],
            'stock adjustments' => ['inventory.adjustments', 200,   200,   200,   403, 403],
            'factories' => ['inventory.factories',           200,   200,   403,   403, 403],
            'areas' => ['inventory.areas',                   200,   200,   403,   403, 403],
            'purchase report' => ['reports.purchase',        200,   200,   403,   403, 403],
            'dealer report' => ['reports.dealers',           200,   200,   403,   403, 403],
            'vouchers' => ['accounts.vouchers.index',        200,   200,   200,   403, 403],
            'new voucher' => ['accounts.vouchers.create',    200,   403,   200,   403, 403],
            'supplier payments' => ['accounts.supplier-payments', 200, 200, 403, 403, 403],
            'purchase orders' => ['purchase.orders.index',   200,   200,   403,   403, 403],
            'dealers' => ['dealers.index',                   200,   200,   403,   403, 403],
            'sales orders' => ['sales.orders.index',         200,   200,   200,   200, 403],
            'retailers' => ['retailers.index',               200,   200,   200,   200, 403],
            'reports' => ['reports.index',                   200,   200,   200,   403, 403],
            'portal orders' => ['portal.orders',             403,   403,   200,   403, 200],   // Admin: no customer account to buy with
            'help' => ['help',                               200,   200,   200,   200, 200],
        ];
    }

    #[DataProvider('matrix')]
    public function test_role_access(string $route, int $admin, int $manager, int $dealer, int $rep, int $retailer): void
    {
        $users = [
            [$this->userWithRole('Admin'), $admin],
            [$this->userWithRole('Manager'), $manager],
            [$this->dealerLogin(), $dealer],
            [$this->userWithRole('Sales Representative'), $rep],
            [$this->retailerLogin(), $retailer],
        ];

        foreach ($users as [$user, $expected]) {
            $this->actingAs($user)->get(route($route))->assertStatus($expected);
        }
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();
    }

    private function dealerLogin(): User
    {
        $area = Area::firstOrCreate(['code' => 'AR1'], ['name' => 'Area 1']);
        $dealer = app(DealerService::class)->create(
            ['code' => 'D'.uniqid(), 'name' => 'Dealer '.uniqid(), 'phone' => '01700000000', 'area_id' => $area->id],
            50000,
        );

        return app(DealerService::class)->addLogin($dealer, ['name' => 'Dealer user', 'email' => uniqid().'@d.test', 'password' => 'secret-123']);
    }

    private function retailerLogin(): User
    {
        $user = $this->userWithRole('Retailer');
        $this->retailer(['user_id' => $user->id]);

        return $user;
    }
}
