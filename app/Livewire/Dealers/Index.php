<?php

namespace App\Livewire\Dealers;

use App\Models\Area;
use App\Models\Dealer;
use App\Services\DealerService;
use App\Support\Tenant;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Head office: the dealers, with their account (credit limit, due) and size. */
#[Layout('layouts.app')]
#[Title('Dealers')]
class Index extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $code = '';
    public string $name = '';
    public string $owner_name = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public ?int $area_id = null;
    public string $credit_limit = '0';
    public bool $active = true;

    // Optional login, only when opening a dealer
    public string $login_email = '';
    public string $login_password = '';

    public string $search = '';
    public string $filter_status = '';

    public function mount(): void
    {
        $this->authorize('dealers.view');
    }

    public function create(): void
    {
        $this->authorize('dealers.manage');
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('dealers.manage');
        $dealer = Dealer::with('account')->findOrFail($id);
        $this->resetForm();
        $this->editingId = $dealer->id;
        $this->fill($dealer->only(['code', 'name', 'area_id', 'active']));
        $this->owner_name = (string) $dealer->owner_name;
        $this->phone = (string) $dealer->phone;
        $this->email = (string) $dealer->email;
        $this->address = (string) $dealer->address;
        $this->credit_limit = (string) ($dealer->account?->credit_limit ?? 0);
        $this->showModal = true;
    }

    public function save(DealerService $dealers): void
    {
        $this->authorize('dealers.manage');

        $data = $this->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('dealers', 'code')->ignore($this->editingId)],
            'name' => 'required|string|max:255',
            'owner_name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'area_id' => 'required|exists:areas,id',
            'credit_limit' => 'required|numeric|min:0',
            'active' => 'boolean',
        ]);

        if (! $this->editingId && $this->login_email !== '') {
            $this->validate([
                'login_email' => 'required|email|max:255|unique:users,email',
                'login_password' => 'required|string|min:8',
            ], [], ['login_email' => __('login email'), 'login_password' => __('password')]);
        }

        $data['code'] = strtoupper($data['code']);
        $creditLimit = (float) $data['credit_limit'];
        unset($data['credit_limit']);
        foreach (['owner_name', 'email', 'address'] as $optional) {
            $data[$optional] = $data[$optional] !== '' ? $data[$optional] : null;
        }

        if ($this->editingId) {
            $dealers->update(Dealer::findOrFail($this->editingId), $data, $creditLimit);
        } else {
            $login = $this->login_email !== ''
                ? ['name' => $data['owner_name'] ?: $data['name'], 'email' => $this->login_email, 'password' => $this->login_password]
                : null;
            $dealer = $dealers->create($data, $creditLimit, $login);
            $this->showModal = false;
            $this->redirect(route('dealers.show', $dealer), navigate: false);

            return;
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'name', 'owner_name', 'phone', 'email', 'address', 'area_id', 'login_email', 'login_password']);
        $this->credit_limit = '0';
        $this->active = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        // Dealers' own customers and staff are outside the head office's view; count them directly.
        $dealers = Tenant::bypass(fn () => Dealer::query()
            ->with(['area', 'account'])
            ->withCount(['retailers', 'ownRepresentatives', 'placedRepresentatives', 'users'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%")
                ->orWhere('owner_name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")))
            ->when($this->filter_status !== '', fn ($q) => $q->where('active', $this->filter_status === 'active'))
            ->orderBy('name')
            ->get());

        $all = Tenant::bypass(fn () => Dealer::with('account')->get());

        return view('livewire.dealers.index', [
            'dealers' => $dealers,
            'areas' => Area::orderBy('name')->get(),
            'summary' => [
                'total' => $all->count(),
                'active' => $all->where('active', true)->count(),
                'due' => (float) $all->sum(fn ($d) => (float) ($d->account?->current_due ?? 0)),
                'credit' => (float) $all->sum(fn ($d) => (float) ($d->account?->credit_limit ?? 0)),
            ],
        ]);
    }
}
