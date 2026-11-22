<?php

namespace App\Livewire\Dashboard;

use App\Models\Damage\DamageRecord;
use App\Models\Damage\RepairJob;
use App\Models\Production\Machine;
use App\Models\Production\ProductionOrder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** The factory at a glance: batches, output, rejects, damage and machines. */
#[Layout('layouts.app')]
#[Title('Factory Dashboard')]
class Factory extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->can('production.view') && ! auth()->user()->dealer_id, 403);
    }

    public function render()
    {
        $monthStart = today()->startOfMonth();
        $done = ProductionOrder::where('status', 'completed')->where('finished_at', '>=', $monthStart);
        $good = (float) (clone $done)->sum('good_quantity');
        $rejected = (float) (clone $done)->sum('rejected_quantity');

        $stats = [
            'completed' => (clone $done)->count(),
            'good' => $good,
            'reject_pct' => $good + $rejected > 0 ? round($rejected / ($good + $rejected) * 100, 1) : 0,
            'cost' => (float) (clone $done)->selectRaw('COALESCE(SUM(material_cost + labour_cost + overhead_cost), 0) as c')->value('c'),
            'planned' => ProductionOrder::where('status', 'planned')->count(),
            'in_progress' => ProductionOrder::where('status', 'in_progress')->count(),
            'qc' => ProductionOrder::where('status', 'qc')->count(),
            'damage_waiting' => DamageRecord::where('status', 'reported')->count(),
            'damage_open_value' => (float) DamageRecord::where('status', 'approved')->selectRaw('COALESCE(SUM(quantity_open * unit_cost), 0) as v')->value('v'),
            'repairs_open' => RepairJob::where('status', 'open')->count(),
            'repairs_late' => RepairJob::where('status', 'open')->whereNotNull('expected_date')->whereDate('expected_date', '<', today())->count(),
        ];

        // Machines whose next service is due within a week (or overdue).
        $machines = Machine::with(['maintenances' => fn ($q) => $q->latest('maintenance_date')->latest('id')])->where('active', true)->get();
        $serviceDue = $machines->map(function ($m) {
            $next = $m->maintenances->first(fn ($x) => $x->next_due_date)?->next_due_date;

            return $next && $next->lte(today()->addDays(7)) ? ['machine' => $m, 'due' => $next] : null;
        })->filter()->sortBy('due')->values();

        // Output by month, last 6 months.
        $chart = ['labels' => [], 'good' => [], 'rejected' => []];
        for ($i = 5; $i >= 0; $i--) {
            $start = today()->startOfMonth()->subMonths($i);
            $end = $start->copy()->endOfMonth()->endOfDay();
            $row = ProductionOrder::where('status', 'completed')->whereBetween('finished_at', [$start, $end])
                ->selectRaw('COALESCE(SUM(good_quantity), 0) as g, COALESCE(SUM(rejected_quantity), 0) as r')->first();
            $chart['labels'][] = $start->locale(app()->getLocale())->translatedFormat('M Y');
            $chart['good'][] = round((float) $row->g, 2);
            $chart['rejected'][] = round((float) $row->r, 2);
        }

        return view('livewire.dashboard.factory', [
            'stats' => $stats,
            'serviceDue' => $serviceDue,
            'chart' => $chart,
            'active' => ProductionOrder::with(['product.unit', 'factory'])->whereIn('status', ['planned', 'in_progress', 'qc'])->orderBy('mfg_date')->limit(8)->get(),
        ]);
    }
}
