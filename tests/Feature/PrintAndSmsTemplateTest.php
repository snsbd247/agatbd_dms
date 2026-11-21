<?php

namespace Tests\Feature;

use App\Http\Controllers\Reports\PdfController;
use App\Livewire\Settings\Messaging;
use App\Models\PaymentCollection;
use App\Models\Retailer;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesOrder;
use App\Models\Settings\AppSetting;
use App\Services\PdfService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sales invoice / money receipt / report PDFs (English and Bangla), who may
 * open them, amount-in-words, and editable SMS text.
 */
class PrintAndSmsTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Retailer $shop;
    private SalesInvoice $invoice;
    private PaymentCollection $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->shop = $this->retailer(['name' => 'Karim Store']);
        $order = SalesOrder::create([
            'number' => 'SO-1', 'order_type' => 'retailer', 'retailer_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse()->id, 'status' => 'completed', 'order_date' => today(),
            'subtotal' => 1500, 'discount' => 0, 'grand_total' => 1500,
        ]);
        $this->invoice = SalesInvoice::create([
            'number' => 'INV-1', 'sales_order_id' => $order->id, 'retailer_id' => $this->shop->id,
            'invoice_date' => today(), 'subtotal' => 1500, 'discount' => 0, 'grand_total' => 1500,
            'paid_amount' => 500, 'due_amount' => 1000, 'status' => 'partial',
        ]);
        $this->payment = PaymentCollection::create([
            'number' => 'COL-1', 'retailer_id' => $this->shop->id, 'collection_date' => today(),
            'amount' => 500, 'method' => 'cash',
        ]);
    }

    private function assertPdf($response): void
    {
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_staff_can_print_sales_invoice_and_receipt_in_both_languages(): void
    {
        foreach (['en', 'bn'] as $locale) {
            $user = $this->userWithRole('Dealer', ['locale' => $locale]);
            $this->actingAs($user);

            $this->assertPdf($this->get(route('sales.invoices.print', $this->invoice)));
            $this->assertPdf($this->get(route('collection.receipt', $this->payment)));
        }
    }

    public function test_every_report_has_a_pdf(): void
    {
        $this->actingAs($this->userWithRole('Dealer'));

        foreach (array_keys(PdfController::REPORTS) as $report) {
            $this->assertPdf($this->get(route('reports.pdf', $report)));
        }

        $this->get(route('reports.pdf', 'nope'))->assertNotFound();
    }

    public function test_report_pdf_needs_report_permission(): void
    {
        $this->actingAs($this->userWithRole('Sales Representative'));

        $this->get(route('reports.pdf', 'sales'))->assertForbidden();
    }

    public function test_retailer_can_print_only_own_documents(): void
    {
        $me = $this->userWithRole('Retailer');
        $this->shop->update(['user_id' => $me->id]);
        $this->actingAs($me);

        $this->assertPdf($this->get(route('portal.sales-invoices.print', $this->invoice)));
        $this->assertPdf($this->get(route('portal.receipts.print', $this->payment)));

        $other = $this->userWithRole('Retailer');
        $this->retailer(['user_id' => $other->id]);
        $this->actingAs($other);

        $this->get(route('portal.sales-invoices.print', $this->invoice))->assertNotFound();
        $this->get(route('portal.receipts.print', $this->payment))->assertNotFound();
        $this->get(route('sales.invoices.print', $this->invoice))->assertForbidden();
    }

    public function test_amount_in_words(): void
    {
        app()->setLocale('en');
        $this->assertSame('Twelve lakh thirty-four thousand five hundred sixty-seven taka and fifty paisa only', PdfService::amountInWords(1234567.50));
        $this->assertSame('Zero taka only', PdfService::amountInWords(0));

        app()->setLocale('bn');
        $this->assertSame('বারো লক্ষ চৌত্রিশ হাজার পাঁচ শত সাতষট্টি টাকা মাত্র', PdfService::amountInWords(1234567));
        $this->assertSame('এক কোটি টাকা মাত্র', PdfService::amountInWords(10000000));
    }

    public function test_sms_text_can_be_changed_and_reset(): void
    {
        $this->actingAs($this->userWithRole('Dealer'));
        AppSetting::put(['sms_sender_name' => 'ACME']);

        $this->assertSame('ACME: Payment received: Tk 1,500.00. Thank you! Remaining due Tk 200.00.',
            SmsService::message('payment_received', ['amount' => 1500, 'due' => 200]));

        Livewire::test(Messaging::class)
            ->set('sms_templates.payment_received', '{retailer}, আপনার ৳{amount} পেয়েছি। বাকি ৳{due}।')
            ->call('saveSms')
            ->assertHasNoErrors();

        $this->assertSame('ACME: Karim, আপনার ৳1,500.00 পেয়েছি। বাকি ৳200.00।',
            SmsService::message('payment_received', ['retailer' => 'Karim', 'amount' => 1500, 'due' => 200]));

        // Back to the default text: nothing custom is stored any more.
        Livewire::test(Messaging::class)->call('resetTemplate', 'payment_received')->call('saveSms');
        $this->assertNull(AppSetting::get('sms_template_payment_received'));
    }
}
