<?php

namespace App\Livewire\Returns\Requests;

use App\Models\Product;
use App\Models\Retailer;
use App\Models\Returns\ReturnRequest;
use App\Models\Returns\ReturnRequestItem;
use App\Models\User;
use App\Notifications\ReturnRequestPending;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New Return Request')]
class Create extends Component
{
    public ?int $retailer_id = null;
    public string $request_date;
    public string $reason = '';

    public array $items = [
        ['product_id' => '', 'quantity' => 1, 'unit_price' => '0.00', 'reason' => ''],
    ];

    public function mount(): void
    {
        $this->authorize('returns.create');
        $this->request_date = today()->toDateString();
    }

    public function updatedItems($value, $key): void
    {
        if (str($key)->endsWith('.product_id')) {
            $index = (int) explode('.', $key)[0];
            $product = $value ? Product::find($value) : null;
            $this->items[$index]['unit_price'] = $product ? (string) $product->sale_price : '0.00';
        }
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => '', 'quantity' => 1, 'unit_price' => '0.00', 'reason' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(NumberGeneratorService $numbers): void
    {
        $this->authorize('returns.create');

        $data = $this->validate([
            'retailer_id' => 'required|exists:retailers,id',
            'request_date' => 'required|date',
            'reason' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
        ]);

        $request = DB::transaction(function () use ($data, $numbers) {
            $request = ReturnRequest::create([
                'number' => $numbers->generate('return_request'),
                'retailer_id' => $data['retailer_id'],
                'requested_by' => Auth::id(),
                'request_date' => $data['request_date'],
                'status' => 'pending',
                'reason' => $data['reason'] ?: null,
            ]);

            foreach ($data['items'] as $index => $item) {
                ReturnRequestItem::create([
                    'return_request_id' => $request->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $this->items[$index]['unit_price'],
                    'reason' => $item['reason'] ?? null,
                ]);
            }

            return $request;
        });

        $recipients = User::role(['Dealer', 'Manager'])->get()->filter(fn (User $u) => $u->wantsNotification('return_request_pending'));
        Notification::send($recipients, new ReturnRequestPending($request));

        $this->redirect(route('returns.show', $request), navigate: false);
    }

    public function render()
    {
        $user = Auth::user();
        $representative = $user->representative;

        $retailers = $representative
            ? Retailer::where('representative_id', $representative->id)->orderBy('name')->get()
            : Retailer::orderBy('name')->get();

        return view('livewire.returns.requests.create', [
            'retailers' => $retailers,
            'products' => Product::orderBy('name')->get(),
        ]);
    }
}
