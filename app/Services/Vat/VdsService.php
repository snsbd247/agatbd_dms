<?php

namespace App\Services\Vat;

use App\Models\Company;
use App\Models\Retailer;
use App\Models\Vat\VdsCertificate;
use App\Services\Accounts\LedgerService;
use App\Services\CollectionService;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\DB;

/**
 * VAT deducted at source (Mushak 6.6).
 *
 * Paying a supplier with VDS:  Dr Payable (cash + VDS)  Cr Bank (cash)  Cr VDS Payable (VDS)
 *   → the VDS is paid to the government with the month's VAT return, and a
 *     6.6 certificate is printed for the supplier.
 *
 * A customer's VDS on our invoice: a collection of that amount that goes
 *   Dr Output VAT  Cr Receivable  (the customer paid our VAT to the
 *   government for us), with their certificate number kept.
 */
class VdsService
{
    public function __construct(
        private LedgerService $ledger,
        private NumberGeneratorService $numbers,
        private CollectionService $collections,
    ) {}

    /**
     * Pay a supplier, optionally keeping back VDS.
     *
     * @return VdsCertificate|null the certificate when VDS was kept back
     */
    public function paySupplier(Company $company, int $moneyAccountId, float $paid, float $vds, string $date, ?string $reference,
        ?string $invoiceNo, ?string $invoiceDate, float $invoiceAmount, string $status): ?VdsCertificate
    {
        $paid = round($paid, 2);
        $vds = round(max(0, $vds), 2);

        return DB::transaction(function () use ($company, $moneyAccountId, $paid, $vds, $date, $reference, $invoiceNo, $invoiceDate, $invoiceAmount, $status) {
            $entry = $this->ledger->post([
                'type' => 'payment',
                'entry_date' => $date,
                'narration' => trim(__('Payment to :name', ['name' => $company->name]).($reference ? ' — '.$reference : '')
                    .($vds > 0 ? ' · '.__('VDS :a kept back', ['a' => number_format($vds, 2)]) : '')),
                'status' => $status,
                'manual' => true,
            ], array_values(array_filter([
                ['account' => 'ap', 'debit' => round($paid + $vds, 2), 'party_type' => 'company', 'party_id' => $company->id, 'narration' => $reference ?: null],
                $paid > 0 ? ['account' => $moneyAccountId, 'credit' => $paid] : null,
                $vds > 0 ? ['account' => 'vds_payable', 'credit' => $vds] : null,
            ])));

            if ($vds <= 0) {
                return null;
            }

            return VdsCertificate::create([
                'number' => $this->numbers->generate('vds_certificate'),
                'direction' => 'issued',
                'company_id' => $company->id,
                'certificate_date' => $date,
                'invoice_no' => $invoiceNo,
                'invoice_date' => $invoiceDate,
                'invoice_amount' => round($invoiceAmount, 2),
                'vat_amount' => $vds,
                'journal_entry_id' => $entry->id,
            ]);
        });
    }

    /** A head-office customer kept back VAT from our invoice and gave us their 6.6. */
    public function receiveFromCustomer(Retailer $customer, float $vat, string $date, string $certificateNo, ?string $invoiceNo): VdsCertificate
    {
        if (! VatService::appliesTo($customer)) {
            throw new \RuntimeException(__('VDS applies only to head-office customers while VAT is on.'));
        }

        return DB::transaction(function () use ($customer, $vat, $date, $certificateNo, $invoiceNo) {
            $collection = $this->collections->record($customer, $vat, 'vds', $date, __('VDS certificate :n', ['n' => $certificateNo]));

            return VdsCertificate::create([
                'number' => $this->numbers->generate('vds_certificate'),
                'direction' => 'received',
                'retailer_id' => $customer->id,
                'certificate_date' => $date,
                'certificate_no' => $certificateNo,
                'invoice_no' => $invoiceNo,
                'vat_amount' => round($vat, 2),
                'collection_id' => $collection->id,
            ]);
        });
    }
}
