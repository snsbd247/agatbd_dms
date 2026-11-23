<?php

namespace Tests\Feature;

use App\Models\Representative;
use App\Models\Sales\SalesOrder;
use App\Models\Scheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Orders taken without internet and sent later. */
class OfflineOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
    }

    /** A representative with one customer of their own and one of someone else. */
    private function rep(): array
    {
        $user = $this->userWithRole('Sales Representative');
        $rep = Representative::create(['user_id' => $user->id, 'employee_code' => 'R-1', 'name' => 'Field Rep', 'active' => true]);
        $mine = $this->retailer(['representative_id' => $rep->id, 'credit_limit' => 1000000]);
        $other = $this->retailer();
        $this->warehouse();

        return [$user, $mine, $other];
    }

    public function test_the_phone_gets_only_the_reps_customers(): void
    {
        [$user, $mine, $other] = $this->rep();
        $this->product(['type' => 'finished_good']);

        $d = $this->actingAs($user)->getJson(route('sales.offline.data'))->assertOk()->json();
        $this->assertSame([$mine->id], array_column($d['customers'], 'id'));
        $this->assertCount(1, $d['products']);
        $this->assertNotEmpty($d['token']);
        $this->get(route('sales.offline'))->assertOk()->assertSee('Save order on this phone');
    }

    public function test_synced_orders_are_priced_by_the_server_once_only(): void
    {
        [$user, $mine, $other] = $this->rep();
        $p = $this->product(['type' => 'finished_good', 'sale_price' => 100]);
        $gift = $this->product(['type' => 'finished_good', 'sale_price' => 10]);
        Scheme::create(['name' => 'Gift', 'type' => 'free_goods', 'audience' => 'all', 'product_id' => $p->id, 'min_qty' => 5,
            'free_product_id' => $gift->id, 'free_qty' => 1, 'starts_on' => today()->subDay(), 'ends_on' => today()->addDay(), 'active' => true]);

        $payload = ['orders' => [
            ['uuid' => 'phone-1', 'retailer_id' => $mine->id, 'order_date' => today()->toDateString(), 'items' => [['product_id' => $p->id, 'quantity' => 10]]],
            ['uuid' => 'phone-2', 'retailer_id' => $other->id, 'order_date' => today()->toDateString(), 'items' => [['product_id' => $p->id, 'quantity' => 1]]],
        ]];

        $res = $this->actingAs($user)->postJson(route('sales.offline.sync'), $payload)->assertOk()->json('results');
        $this->assertTrue($res[0]['ok']);
        $this->assertFalse($res[1]['ok']);   // not this rep's customer

        $order = SalesOrder::where('client_uuid', 'phone-1')->first();
        $this->assertSame(1000.0, (float) $order->grand_total);
        $this->assertSame('representative', $order->order_type);
        $this->assertSame(2.0, (float) $order->items()->where('is_free', true)->value('quantity'));

        // The same batch again (the phone lost the reply): no second order.
        $again = $this->postJson(route('sales.offline.sync'), $payload)->json('results');
        $this->assertTrue($again[0]['again']);
        $this->assertSame(1, SalesOrder::count());
    }

    public function test_people_who_cannot_take_orders_are_refused(): void
    {
        $this->actingAs($this->userWithRole('HR Manager'));
        $this->getJson(route('sales.offline.data'))->assertForbidden();
        $this->postJson(route('sales.offline.sync'), ['orders' => []])->assertForbidden();
    }
}
