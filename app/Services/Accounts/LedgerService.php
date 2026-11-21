<?php

namespace App\Services\Accounts;

use App\Models\Accounts\Account;
use App\Models\Accounts\FiscalYearClosing;
use App\Models\Accounts\JournalEntry;
use App\Services\NumberGeneratorService;
use App\Support\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Writes vouchers. Every voucher must balance (total debit = total credit),
 * use only postable accounts, and (for vouchers entered by hand) fall in an
 * open fiscal year. Posted vouchers are never edited; reverse() undoes one.
 */
class LedgerService
{
    public function __construct(private NumberGeneratorService $numbers) {}

    /**
     * @param  array{type: string, entry_date: string|\DateTimeInterface, dealer_id?: ?int, narration?: ?string, status?: string, source?: ?\Illuminate\Database\Eloquent\Model, source_event?: ?string, reversal_of_id?: ?int, manual?: bool}  $header
     * @param  array<int, array{account: Account|string|int, debit?: float, credit?: float, party_type?: ?string, party_id?: ?int, narration?: ?string}>  $lines
     */
    public function post(array $header, array $lines): JournalEntry
    {
        $date = \Carbon\Carbon::parse($header['entry_date'])->toDateString();
        $dealerId = array_key_exists('dealer_id', $header) ? $header['dealer_id'] : Tenant::dealerId();
        $status = $header['status'] ?? 'posted';

        $rows = $this->normalise($lines);
        $this->assertBalanced($rows);

        if (($header['manual'] ?? false) && $this->isClosed($dealerId, $date)) {
            throw new \RuntimeException(__('The books are closed for :date. Choose a date in an open fiscal year.', ['date' => $date]));
        }

        return Tenant::bypass(fn () => DB::transaction(function () use ($header, $rows, $date, $dealerId, $status) {
            $type = $header['type'];
            $entry = JournalEntry::create([
                'number' => $this->numbers->generate(JournalEntry::TYPES[$type][1] ?? 'journal_voucher'),
                'dealer_id' => $dealerId,
                'type' => $type,
                'entry_date' => $date,
                'narration' => $header['narration'] ?? null,
                'status' => $status,
                'source_type' => isset($header['source']) ? $header['source']->getMorphClass() : null,
                'source_id' => isset($header['source']) ? $header['source']->getKey() : null,
                'source_event' => $header['source_event'] ?? null,
                'reversal_of_id' => $header['reversal_of_id'] ?? null,
                'created_by' => Auth::id(),
                'approved_by' => $status === 'posted' ? Auth::id() : null,
                'approved_at' => $status === 'posted' ? now() : null,
            ]);

            foreach ($rows as $row) {
                $entry->lines()->create($row);
            }

            return $entry->load('lines');
        }));
    }

    /** Approve a draft: it starts counting in the books. */
    public function approve(JournalEntry $entry): void
    {
        if ($entry->status !== 'draft') {
            throw new \RuntimeException(__('Only a draft voucher can be approved.'));
        }

        if ($this->isClosed($entry->dealer_id, $entry->entry_date->toDateString())) {
            throw new \RuntimeException(__('The books are closed for :date. Choose a date in an open fiscal year.', ['date' => $entry->entry_date->toDateString()]));
        }

        $entry->update(['status' => 'posted', 'approved_by' => Auth::id(), 'approved_at' => now()]);
    }

    /** Undo a posted voucher with an opposite one dated $date (default today). */
    public function reverse(JournalEntry $entry, ?string $date = null, ?string $reason = null): JournalEntry
    {
        if ($entry->status !== 'posted') {
            throw new \RuntimeException(__('Only a posted voucher can be reversed.'));
        }
        if ($entry->reversedBy()->exists() || $entry->reversal_of_id) {
            throw new \RuntimeException(__('This voucher has already been reversed or is itself a reversal.'));
        }

        $lines = $entry->lines->map(fn ($l) => [
            'account' => (int) $l->account_id,
            'debit' => (float) $l->credit,
            'credit' => (float) $l->debit,
            'party_type' => $l->party_type,
            'party_id' => $l->party_id,
            'narration' => $l->narration,
        ])->all();

        return $this->post([
            'type' => $entry->type === 'auto' ? 'journal' : $entry->type,
            'entry_date' => $date ?? today()->toDateString(),
            'dealer_id' => $entry->dealer_id,
            'narration' => trim(__('Reversal of :number', ['number' => $entry->number]).($reason ? ' — '.$reason : '')),
            'reversal_of_id' => $entry->id,
            'manual' => true,
        ], $lines);
    }

    /** Draft vouchers can simply be thrown away. */
    public function discard(JournalEntry $entry): void
    {
        if ($entry->status !== 'draft') {
            throw new \RuntimeException(__('Only a draft voucher can be deleted. Reverse a posted one instead.'));
        }

        $entry->delete();
    }

    public function isClosed(?int $dealerId, string $date): bool
    {
        return Tenant::bypass(fn () => FiscalYearClosing::query()
            ->when($dealerId === null, fn ($q) => $q->whereNull('dealer_id'), fn ($q) => $q->where('dealer_id', $dealerId))
            ->whereDate('end_date', '>=', $date)
            ->exists());
    }

    /** @return array<int, array<string, mixed>> */
    private function normalise(array $lines): array
    {
        $rows = [];

        foreach ($lines as $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($debit < 0 || $credit < 0) {
                throw new \RuntimeException(__('Amounts cannot be negative.'));
            }
            if ($debit == 0 && $credit == 0) {
                continue;   // automatic postings may produce empty lines (no discount …)
            }
            if ($debit > 0 && $credit > 0) {
                throw new \RuntimeException(__('A line is either a debit or a credit, not both.'));
            }

            $account = $this->resolve($line['account']);

            if ($account->is_group) {
                throw new \RuntimeException(__(':account is a group; post to one of the accounts under it.', ['account' => $account->name]));
            }

            if (in_array($account->system_key, ['ar', 'ap'], true) && empty($line['party_type'])) {
                throw new \RuntimeException(__(':account needs a customer or supplier on the line.', ['account' => $account->name]));
            }

            $rows[] = [
                'account_id' => $account->id,
                'debit' => $debit,
                'credit' => $credit,
                'party_type' => $line['party_type'] ?? null,
                'party_id' => $line['party_id'] ?? null,
                'narration' => $line['narration'] ?? null,
            ];
        }

        return $rows;
    }

    private function assertBalanced(array $rows): void
    {
        if (count($rows) < 2) {
            throw new \RuntimeException(__('A voucher needs at least one debit and one credit line.'));
        }

        $debit = round(array_sum(array_column($rows, 'debit')), 2);
        $credit = round(array_sum(array_column($rows, 'credit')), 2);

        if (abs($debit - $credit) > 0.005) {
            throw new \RuntimeException(__('Debit (:d) and credit (:c) must be equal.', ['d' => number_format($debit, 2), 'c' => number_format($credit, 2)]));
        }
    }

    private function resolve(Account|string|int $account): Account
    {
        if ($account instanceof Account) {
            return $account;
        }

        return is_int($account)
            ? Account::withoutGlobalScopes()->findOrFail($account)
            : Account::bySystemKey($account);
    }
}
