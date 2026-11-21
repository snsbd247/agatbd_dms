<?php

namespace Tests\Feature;

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
        //                     route                      Dealer Manager Rep  Retailer
        return [
            'settings users' => ['settings.users',          200,   403,   403, 403],
            'payroll' => ['payroll.index',                   200,   403,   403, 403],
            'backup' => ['more.backup-restore',              200,   403,   403, 403],
            'email & sms' => ['settings.messaging',          200,   403,   403, 403],
            'audit log' => ['settings.audit-log',            200,   403,   403, 403],
            'stock adjustments' => ['inventory.adjustments', 200,   200,   403, 403],
            'purchase orders' => ['purchase.orders.index',   200,   200,   403, 403],
            'sales orders' => ['sales.orders.index',         200,   200,   200, 403],
            'retailers' => ['retailers.index',               200,   200,   200, 403],
            'reports' => ['reports.index',                   200,   200,   403, 403],
            'help' => ['help',                               200,   200,   200, 200],
        ];
    }

    #[DataProvider('matrix')]
    public function test_role_access(string $route, int $dealer, int $manager, int $rep, int $retailer): void
    {
        foreach (['Dealer' => $dealer, 'Manager' => $manager, 'Sales Representative' => $rep, 'Retailer' => $retailer] as $role => $expected) {
            $this->actingAs($this->userWithRole($role))
                ->get(route($route))
                ->assertStatus($expected);
        }
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();
    }
}
