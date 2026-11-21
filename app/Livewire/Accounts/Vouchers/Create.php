<?php

namespace App\Livewire\Accounts\Vouchers;

use App\Livewire\Accounts\Concerns\PicksParties;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalEntry;
use App\Services\Accounts\LedgerService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A voucher entered by hand: journal, payment, receipt or contra. Saved as a
 * draft, or posted straight away by someone allowed to approve.
 */
#[Layout('layouts.app')]
#[Title('New Voucher')]
class Create extends Component
{
    use PicksParties;

    #[Url]
    public string $type = 'journal';

    public string $entry_date = '';
    public string $narration = '';

    /** @var array<int, array{account_id: int|string|null, party_id: int|string|null, debit: string, credit: string, narration: string}> */
    public array $lines = [];

    public function mount(): void
    {
        $this->authorize('accounts.manage');
        abort_unless(in_array($this->type, JournalEntry::MANUAL_TYPES, true), 404);
        $this->entry_date = today()->toDateString();
        $this->lines = [$this->blank(), $this->blank()];
    }

    private function blank(): array
    {
        return ['account_id' => null, 'party_id' => null, 'debit' => '', 'credit' => '', 'narration' => ''];
    }

    public function addLine(): void
    {
        $this->lines[] = $this->blank();
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
    }

    public function save(bool $post = false): void
    {
        $this->authorize('accounts.manage');
        $post = $post && auth()->user()->can('accounts.approve');

        $this->validate([
            'type' => 'required|in:'.implode(',', JournalEntry::MANUAL_TYPES),
            'entry_date' => 'required|date',
            'narration' => 'nullable|string|max:1000',
            'lines' => 'array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'lines.*.narration' => 'nullable|string|max:255',
        ], [], ['lines.*.account_id' => __('account')]);

        $accounts = Account::whereIn('id', collect($this->lines)->pluck('account_id'))->get()->keyBy('id');
        $rows = [];

        foreach ($this->lines as $i => $line) {
            $account = $accounts[(int) $line['account_id']] ?? null;
            if (! $account) {
                $this->addError("lines.$i.account_id", __('Choose an account you can use.'));

                return;
            }

            $partyType = $this->partyTypeFor($account);
            if (in_array($partyType, ['retailer', 'company', 'representative', 'employee'], true) && blank($line['party_id'])) {
                $this->addError("lines.$i.party_id", __('Choose who this is with.'));

                return;
            }

            $rows[] = [
                'account' => (int) $account->id,
                'debit' => (float) ($line['debit'] ?: 0),
                'credit' => (float) ($line['credit'] ?: 0),
                'party_type' => $partyType,
                'party_id' => $partyType && $partyType !== 'head_office' ? (int) $line['party_id'] : null,
                'narration' => $line['narration'] ?: null,
            ];
        }

        try {
            $entry = app(LedgerService::class)->post([
                'type' => $this->type,
                'entry_date' => $this->entry_date,
                'narration' => $this->narration ?: null,
                'status' => $post ? 'posted' : 'draft',
                'manual' => true,
            ], $rows);
        } catch (\RuntimeException $e) {
            $this->addError('lines', $e->getMessage());

            return;
        }

        $this->redirect(route('accounts.vouchers.show', $entry), navigate: false);
    }

    public function render()
    {
        $accounts = Account::postable()->orderBy('code')->get();
        $byId = $accounts->keyBy('id');

        $partyTypes = [];
        foreach ($this->lines as $i => $line) {
            $partyTypes[$i] = $this->partyTypeFor($byId[(int) $line['account_id']] ?? null);
        }

        $debit = round(collect($this->lines)->sum(fn ($l) => (float) ($l['debit'] ?: 0)), 2);
        $credit = round(collect($this->lines)->sum(fn ($l) => (float) ($l['credit'] ?: 0)), 2);

        return view('livewire.accounts.vouchers.create', [
            'accounts' => $accounts,
            'partyTypes' => $partyTypes,
            'parties' => $this->partyOptions(),
            'debit' => $debit,
            'credit' => $credit,
            'hint' => [
                'payment' => __('Payment: debit what the money was for (an expense, a supplier …), credit the cash or bank it came from.'),
                'receipt' => __('Receipt: debit the cash or bank that received it, credit where it came from (a customer, other income …).'),
                'contra' => __('Contra: money moving between your own cash and bank accounts.'),
                'journal' => __('Journal: any other entry, e.g. depreciation or a correction.'),
            ][$this->type],
        ])->title(__(JournalEntry::TYPES[$this->type][0]));
    }
}
