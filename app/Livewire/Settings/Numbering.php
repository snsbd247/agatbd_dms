<?php

namespace App\Livewire\Settings;

use App\Models\Settings\NumberingSequence;
use App\Services\NumberGeneratorService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Numbering Sequences')]
class Numbering extends Component
{
    public function render()
    {
        $year = (int) date('Y');
        $sequences = NumberingSequence::where('year', $year)->get()->keyBy('key');

        $rows = collect(NumberGeneratorService::PREFIXES)->map(function (string $prefix, string $key) use ($sequences, $year) {
            $sequence = $sequences->get($key);
            $lastNumber = $sequence?->last_number ?? 0;

            return [
                'key' => $key,
                'prefix' => $prefix,
                'last_number' => $lastNumber,
                'next_number' => sprintf('%s-%d-%06d', $prefix, $year, $lastNumber + 1),
            ];
        })->values();

        return view('livewire.settings.numbering', [
            'rows' => $rows,
            'year' => $year,
        ]);
    }
}
