<?php

namespace Tests\Feature;

use App\Support\Modules;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** The menu: grouped by module, every link real, every page reachable. */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_every_menu_link_points_to_a_real_route(): void
    {
        foreach (Navigation::definition() as $section) {
            foreach ($section['groups'] as $group) {
                foreach ($group['links'] as $link) {
                    $this->assertTrue(Route::has($link['route']), "Missing route {$link['route']}");
                }
            }
        }
    }

    public function test_every_page_is_in_the_menu(): void
    {
        $inMenu = collect(Navigation::definition())->flatMap(fn ($s) => collect($s['groups'])->flatMap(fn ($g) => collect($g['links'])->pluck('route')))->all();

        // Pages reached from another page (create forms, exports, dashboards ...).
        $reachedElsewhere = '/^(home$|dashboard|notifications|search|help|business|more\.(index|about|backup\.)|account\.|locale|login|logout|password|portal\.|livewire|storage|up$|.*\.(create|export|exports?\..*|pdf|print|generate|download)$|reports\.export\.|reports\.index|master-data\.index|settings\.data-import\.template)/';

        $missing = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods()) && $r->getName() && ! str_contains($r->uri(), '{'))
            ->map(fn ($r) => $r->getName())
            ->reject(fn ($n) => in_array($n, $inMenu, true) || preg_match($reachedElsewhere, $n))
            ->values()->all();

        $this->assertSame([], $missing, 'Pages not in the menu: '.implode(', ', $missing));
    }

    public function test_admin_sees_the_sections_in_order(): void
    {
        $this->actingAs($this->userWithRole('Admin'));
        $labels = collect(Navigation::for(auth()->user()))->pluck('label')->filter()->values()->all();

        $this->assertSame(['Sales & Customers', 'Purchase & Stock', 'Manufacturing', 'People', 'Finance', 'Reports', 'Setup'], $labels);
    }

    public function test_switched_off_modules_and_head_office_pages_are_hidden(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->actingAs($admin);
        Modules::set('production', false);

        $routes = collect(Navigation::for($admin))->flatMap(fn ($s) => collect($s['groups'])->flatMap(fn ($g) => collect($g['links'])->pluck('route')));
        $this->assertNotContains('production.orders.index', $routes);
        $this->assertNotContains('inventory.factories', $routes);
        $this->assertContains('sales.orders.index', $routes);
    }

    public function test_the_current_page_opens_its_group(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        $this->get(route('delivery.invoices.index'))->assertOk()->assertSee('Delivery Invoices');
        $menu = Navigation::for(auth()->user());
        $this->assertSame('Sales', Navigation::activeGroupLabel($menu));

        $this->get(route('more.index'))->assertOk()->assertSee('Purchase &amp; Stock', false)->assertSee('Backup &amp; Restore', false);
    }
}
