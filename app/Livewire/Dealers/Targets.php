<?php

namespace App\Livewire\Dealers;

use App\Models\Dealer;
use App\Models\DealerTarget;
use App\Services\DealerTargetService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Dealers' monthly purchase targets, achievement and incentive. */
#[Layout('layouts.app')]
#[Title('Dealer Targets')]
class Targets extends Component
{
    #[Url]
    public string $month = '';

    /** @var array<int, array{target: string, percent: string}> dealer id => inputs */
    public array $inputs = [];

    public function mount(): void
    {
        $this->authorize('dealers.view');
        abort_if(auth()->user()->dealer_id, 403);
        $this->month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : today()->format('Y-m');
        $this->loadInputs();
    }

    public function updatedMonth(): void
    {
        $this->month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : today()->format('Y-m');
        $this->loadInputs();
    }

    private function loadInputs(): void
    {
        $targets = DealerTarget::where('period', $this->month)->get()->keyBy('dealer_id');
        $this->inputs = [];
        foreach (Dealer::orderBy('name')->get() as $d) {
            $t = $targets[$d->id] ?? null;
            $this->inputs[$d->id] = [
                'target' => $t ? (string) (float) $t->target_amount : '',
                'percent' => $t ? (string) (float) $t->incentive_percent : '',
            ];
        }
    }

    public function save(): void
    {
        $this->authorize('dealers.manage');
        $this->validate([
            'inputs.*.target' => 'nullable|numeric|min:0',
            'inputs.*.percent' => 'nullable|numeric|min:0|max:100',
        ]);

        foreach ($this->inputs as $dealerId => $in) {
            $existing = DealerTarget::where('dealer_id', $dealerId)->where('period', $this->month)->first();
            if ($existing?->posted_at) {
                continue;   // posted months are frozen
            }
            if ($in['target'] === '' || (float) $in['target'] <= 0) {
                $existing?->delete();

                continue;
            }
            DealerTarget::updateOrCreate(
                ['dealer_id' => $dealerId, 'period' => $this->month],
                ['target_amount' => (float) $in['target'], 'incentive_percent' => (float) ($in['percent'] ?: 0)],
            );
        }
        session()->flash('success', __('Targets saved for :m.', ['m' => $this->month]));
    }

    /** Copy last month's targets into this month (only where none is set). */
    public function copyLastMonth(): void
    {
        $this->authorize('dealers.manage');
        $prev = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $this->month.'-01')->subMonth()->format('Y-m');
        foreach (DealerTarget::where('period', $prev)->get() as $t) {
            DealerTarget::firstOrCreate(['dealer_id' => $t->dealer_id, 'period' => $this->month],
                ['target_amount' => $t->target_amount, 'incentive_percent' => $t->incentive_percent]);
        }
        $this->loadInputs();
    }

    public function postIncentive(int $targetId, DealerTargetService $service): void
    {
        $this->authorize('dealers.manage');
        $this->authorize('accounts.manage');

        try {
            $service->post(DealerTarget::findOrFail($targetId));
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }
        session()->flash('success', __('Incentive posted to the dealer\'s account.'));
    }

    public function render(DealerTargetService $service)
    {
        $targets = DealerTarget::with('journalEntry')->where('period', $this->month)->get()->keyBy('dealer_id');
        $rows = Dealer::orderBy('name')->get()->map(function ($d) use ($targets, $service) {
            $t = $targets[$d->id] ?? null;
            $achieved = $t ? ($t->posted_at ? (float) $t->achieved_amount : $service->achieved($t)) : 0.0;

            return [
                'dealer' => $d,
                'target' => $t,
                'achieved' => $achieved,
                'percent' => $t && (float) $t->target_amount > 0 ? min(999, round($achieved / (float) $t->target_amount * 100)) : null,
                'incentive' => $t ? ($t->posted_at ? (float) $t->incentive_amount : $service->incentiveFor($t, $achieved)) : 0.0,
            ];
        });

        return view('livewire.dealers.targets', [
            'rows' => $rows,
            'monthOver' => \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $this->month.'-01')->endOfMonth()->isPast(),
        ]);
    }
}
