<?php

namespace App\Livewire\Settings;

use App\Models\Settings\AuditLog;
use App\Support\Modules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Settings → Business Type & Modules: pick trading, manufacturing or both
 * (loads that template), then switch off what this business does not use.
 */
#[Layout('layouts.app')]
#[Title('Business Type & Modules')]
class ModuleSettings extends Component
{
    /** Type picked on the page but not applied yet (waiting for confirmation). */
    public ?string $pendingType = null;

    public ?string $message = null;

    public function mount(): void
    {
        $this->authorize('settings.manage');
    }

    public function pickType(string $type): void
    {
        $this->authorize('settings.manage');
        abort_unless(isset(Modules::TYPES[$type]), 422);
        $this->pendingType = $type === Modules::type() ? null : $type;
    }

    public function applyType(): void
    {
        $this->authorize('settings.manage');
        if (! $this->pendingType) {
            return;
        }
        $old = ['type' => Modules::type(), 'off' => Modules::off()];
        Modules::applyTemplate($this->pendingType);
        $this->audit('modules.template', $old);
        $this->message = __('":t" template loaded. Switch off anything you do not need.', ['t' => __(Modules::TYPES[$this->pendingType])]);
        $this->pendingType = null;
    }

    public function toggle(string $key): void
    {
        $this->authorize('settings.manage');
        $old = ['type' => Modules::type(), 'off' => Modules::off()];
        $on = ! Modules::enabled($key);
        $also = Modules::set($key, $on);
        $this->audit('modules.'.($on ? 'on' : 'off'), $old);

        $label = __(Modules::all()[$key]['label']);
        $this->message = $on ? __(':m switched on.', ['m' => $label]) : __(':m switched off. Its data is kept.', ['m' => $label]);
        if ($also) {
            $this->message .= ' '.__('Also changed: :list.', ['list' => collect($also)->map(fn ($k) => __(Modules::all()[$k]['label']))->implode(', ')]);
        }
    }

    private function audit(string $action, array $old): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => 'modules',
            'auditable_id' => 0,
            'old_values' => $old,
            'new_values' => ['type' => Modules::type(), 'off' => Modules::off()],
            'ip_address' => request()->ip(),
        ]);
    }

    /** Work still open in a module, shown as a warning before switching it off. */
    private function openWork(): array
    {
        $checks = [
            'production' => [fn () => \App\Models\Production\ProductionOrder::whereIn('status', ['planned', 'in_progress', 'qc'])->count(), ':n batches not finished'],
            'damage' => [fn () => \App\Models\Damage\DamageRecord::whereIn('status', ['reported', 'approved'])->count(), ':n damage records open'],
            'returns' => [fn () => \App\Models\Returns\ReturnRequest::whereIn('status', ['pending', 'approved'])->count(), ':n returns open'],
            'warranty' => [fn () => \App\Models\Warranty\WarrantyClaim::where('status', 'pending')->count(), ':n warranty claims pending'],
            'dealers' => [fn () => \App\Models\Dealer::where('active', true)->count(), ':n active dealers will not be able to log in to their panel'],
        ];
        $out = [];
        foreach ($checks as $key => [$count, $text]) {
            $n = (int) rescue($count, 0, false);
            if ($n > 0) {
                $out[$key] = __($text, ['n' => $n]);
            }
        }

        return $out;
    }

    public function render()
    {
        $type = Modules::type();
        $modules = collect(Modules::all())->map(fn ($m, $key) => $m + [
            'key' => $key,
            'enabled' => Modules::enabled($key),
            'inTemplate' => $type !== null && in_array($type, $m['on'], true),
            'blockedBy' => collect($m['requires'])->reject(fn ($r) => Modules::enabled($r))->map(fn ($r) => __(Modules::all()[$r]['label']))->values()->all(),
            'dependents' => collect(Modules::dependents($key))->filter(fn ($d) => Modules::enabled($d))->map(fn ($d) => __(Modules::all()[$d]['label']))->values()->all(),
        ]);

        // Modules the chosen type does not have are left out.
        $visible = $modules->filter(fn ($m) => $type === null || in_array($type, $m['available'], true));

        return view('livewire.settings.module-settings', [
            'type' => $type,
            'groups' => $visible->groupBy('group'),
            'openWork' => $this->openWork(),
            'pendingDiff' => $this->pendingType ? $this->templateDiff($this->pendingType) : null,
        ]);
    }

    /** What applying a template would switch on and off. */
    private function templateDiff(string $type): array
    {
        $on = $off = [];
        foreach (Modules::all() as $key => $m) {
            if ($m['core'] || $m['coming'] !== null) {
                continue;
            }
            $willBeOn = in_array($type, $m['available'], true) && in_array($type, $m['on'], true);
            $isOn = Modules::enabled($key);
            if ($willBeOn && ! $isOn) {
                $on[] = __($m['label']);
            } elseif (! $willBeOn && $isOn) {
                $off[] = __($m['label']);
            }
        }

        return ['on' => $on, 'off' => $off];
    }
}
