<?php

namespace Tests\Feature;

use App\Services\NumberGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_are_sequential_per_type_and_year(): void
    {
        $numbers = app(NumberGeneratorService::class);
        $year = now()->year;

        $this->assertSame("SO-{$year}-000001", $numbers->generate('sales_order'));
        $this->assertSame("SO-{$year}-000002", $numbers->generate('sales_order'));
        $this->assertSame("DI-{$year}-000001", $numbers->generate('delivery_invoice'));
    }

    public function test_sequence_restarts_in_a_new_year(): void
    {
        $numbers = app(NumberGeneratorService::class);
        $numbers->generate('sales_order');

        $this->travelTo(now()->addYear()->startOfYear());
        $this->assertSame('SO-'.now()->year.'-000001', $numbers->generate('sales_order'));
    }

    public function test_unknown_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(NumberGeneratorService::class)->generate('nonsense');
    }
}
