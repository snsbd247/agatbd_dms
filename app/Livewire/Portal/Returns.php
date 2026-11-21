<?php

namespace App\Livewire\Portal;

use App\Models\Product;
use App\Models\Returns\ReturnRequest;
use App\Models\Returns\ReturnRequestItem;
use App\Models\Sales\SalesInvoiceItem;
use App\Models\User;
use App\Notifications\ReturnRequestPending;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A retailer asks to return goods they bought. Only products that were
 * invoiced to this retailer can be picked, the quantity can't be more than
 * what was bought (minus what is already in open or accepted requests), and
 * the price is the last invoiced price, never a value from the browser.
 */
#[Layout('layouts.app')]
#[Title('My Returns')]
class Returns extends Component
{
    use ForCurrentRetailer, WithPagination;

    public bool $showForm = false;
    public string $reason = '';
    public array $items = [];

    public function mount(): void
    {
        $this->retailer();
        $this->resetForm();
    }

    public function openForm(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    private function resetForm(): void
    {
        $this->reason = '';
        $this->items = [['product_id' => '', 'quantity' => 1, 'reason' => '']];
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => '', 'quantity' => 1, 'reason' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /**
     * Products this retailer has bought, with how many can still be returned
     * and the last price they paid.
     *
     * @return Collection<int, object{product: Product, returnable: float, unit_price: float}>
     */
    private function returnable(): Collection
    {
        $retailerId = $this->retailer()->id;

        $bought = SalesInvoiceItem::query()
            ->whereHas('salesInvoice', fn ($q) => $q->where('retailer_id', $retailerId))
            ->selectRaw('product_id, SUM(quantity) as qty, MAX(id) as last_id')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        if ($bought->isEmpty()) {
            return collect();
        }

        $lastPrice = SalesInvoiceItem::whereIn('id', $bought->pluck('last_id'))->pluck('unit_price', 'product_id');

        $requested = ReturnRequestItem::query()
            ->whereHas('returnRequest', fn ($q) => $q->where('retailer_id', $retailerId)->where('status', '!=', 'rejected'))
            ->selectRaw('product_id, SUM(quantity) as qty')
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        return Product::whereIn('id', $bought->keys())->orderBy('name')->get()
            ->map(fn (Product $p) => (object) [
                'product' => $p,
                'returnable' => max(0, (float) $bought[$p->id]->qty - (float) ($requested[$p->id] ?? 0)),
                'unit_price' => (float) ($lastPrice[$p->id] ?? 0),
            ])
            ->filter(fn ($row) => $row->returnable > 0)
            ->keyBy(fn ($row) => $row->product->id);
    }

    public function save(NumberGeneratorService $numbers): void
    {
        $retailer = $this->retailer();
        $returnable = $this->returnable();

        $this->validate([
            'reason' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.reason' => 'nullable|string|max:255',
        ], [
            'items.*.product_id.distinct' => __('Each product can be added only once.'),
        ]);

        foreach ($this->items as $index => $item) {
            $row = $returnable->get((int) $item['product_id']);

            if (! $row) {
                $this->addError("items.{$index}.product_id", __('You have not bought this product.'));

                return;
            }

            if ((float) $item['quantity'] > $row->returnable) {
                $this->addError("items.{$index}.quantity", __('You can return at most :qty.', ['qty' => rtrim(rtrim(number_format($row->returnable, 3), '0'), '.')]));

                return;
            }
        }

        $request = DB::transaction(function () use ($numbers, $retailer, $returnable) {
            $request = ReturnRequest::create([
                'number' => $numbers->generate('return_request'),
                'retailer_id' => $retailer->id,
                'requested_by' => Auth::id(),
                'request_date' => today(),
                'status' => 'pending',
                'reason' => $this->reason ?: null,
            ]);

            foreach ($this->items as $item) {
                ReturnRequestItem::create([
                    'return_request_id' => $request->id,
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (float) $item['quantity'],
                    'unit_price' => $returnable->get((int) $item['product_id'])->unit_price,
                    'reason' => ($item['reason'] ?? '') ?: null,
                ]);
            }

            return $request;
        });

        $recipients = User::role(['Dealer', 'Manager'])->get()->filter(fn (User $u) => $u->wantsNotification('return_request_pending'));
        Notification::send($recipients, new ReturnRequestPending($request));

        $this->showForm = false;
        $this->resetForm();
        session()->flash('status', __('Return request :number was sent to the dealer.', ['number' => $request->number]));
    }

    public function render()
    {
        $retailer = $this->retailer();

        $requests = ReturnRequest::query()
            ->where('retailer_id', $retailer->id)
            ->with('items.product')
            ->latest('request_date')
            ->latest('id')
            ->paginate(10);

        return view('livewire.portal.returns', [
            'requests' => $requests,
            'returnable' => $this->showForm ? $this->returnable() : collect(),
        ]);
    }
}
