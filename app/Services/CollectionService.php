<?php

namespace App\Services;

use App\Models\PaymentCollection;
use App\Models\Retailer;
use App\Services\Accounts\AutoPosting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Recording money received from a customer: the collection, its share-out
 * over the oldest unpaid invoices, the customer's due and ledger, and the
 * voucher. Used by the Record Payment screen and by cleared cheques.
 */
class CollectionService
{
    public function __construct(
        private NumberGeneratorService $numbers,
        private PaymentAllocationService $allocator,
        private AutoPosting $posting,
    ) {}

    public function record(Retailer $retailer, float $amount, string $method, string $date, ?string $reference = null): PaymentCollection
    {
        return DB::transaction(function () use ($retailer, $amount, $method, $date, $reference) {
            $collection = PaymentCollection::create([
                'number' => $this->numbers->generate('collection'),
                'retailer_id' => $retailer->id,
                'collected_by' => Auth::id(),
                'collection_date' => $date,
                'amount' => round($amount, 2),
                'method' => $method,
                'reference_no' => $reference ?: null,
            ]);

            $this->allocator->allocate($retailer, round($amount, 2), $collection);
            $this->posting->collection($collection);

            return $collection;
        });
    }
}
