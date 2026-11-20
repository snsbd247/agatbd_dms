<?php

namespace App\Livewire\Settings\WarrantyPolicies;

use App\Models\Category;
use App\Models\Product;
use App\Models\Settings\AuditLog;
use App\Models\Warranty\WarrantyPolicy;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Warranty Policies')]
class Index extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $scope_type = 'product';
    public ?int $product_id = null;
    public ?int $category_id = null;
    public string $duration_months = '';
    public string $terms = '';

    public function create(): void
    {
        $this->authorize('settings.manage');
        $this->reset(['editingId', 'scope_type', 'product_id', 'category_id', 'duration_months', 'terms']);
        $this->scope_type = 'product';
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('settings.manage');
        $policy = WarrantyPolicy::findOrFail($id);

        $this->editingId = $policy->id;
        $this->scope_type = $policy->product_id ? 'product' : 'category';
        $this->product_id = $policy->product_id;
        $this->category_id = $policy->category_id;
        $this->duration_months = (string) $policy->duration_months;
        $this->terms = (string) $policy->terms;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('settings.manage');

        $this->validate([
            'duration_months' => 'required|integer|min:1|max:120',
            'terms' => 'nullable|string|max:2000',
        ]);

        $productId = $this->scope_type === 'product' ? $this->product_id : null;
        $categoryId = $this->scope_type === 'category' ? $this->category_id : null;

        if (! $productId && ! $categoryId) {
            $this->addError('product_id', 'Select a product or a category.');

            return;
        }

        $duplicate = WarrantyPolicy::query()
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->exists();

        if ($duplicate) {
            $this->addError('product_id', 'A policy already exists for this '.($productId ? 'product' : 'category').'.');

            return;
        }

        $data = [
            'product_id' => $productId,
            'category_id' => $categoryId,
            'duration_months' => (int) $this->duration_months,
            'terms' => $this->terms ?: null,
        ];

        if ($this->editingId) {
            $policy = WarrantyPolicy::findOrFail($this->editingId);
            $original = $policy->getOriginal();
            $policy->update($data);

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'warranty_policy.updated',
                'auditable_type' => WarrantyPolicy::class,
                'auditable_id' => $policy->id,
                'old_values' => $original,
                'new_values' => $policy->getChanges(),
                'ip_address' => request()->ip(),
            ]);
        } else {
            $policy = WarrantyPolicy::create($data);

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'warranty_policy.created',
                'auditable_type' => WarrantyPolicy::class,
                'auditable_id' => $policy->id,
                'old_values' => null,
                'new_values' => $data,
                'ip_address' => request()->ip(),
            ]);
        }

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        $this->authorize('settings.manage');
        $policy = WarrantyPolicy::findOrFail($id);
        $attributes = $policy->getAttributes();
        $policy->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'warranty_policy.deleted',
            'auditable_type' => WarrantyPolicy::class,
            'auditable_id' => $id,
            'old_values' => $attributes,
            'new_values' => null,
            'ip_address' => request()->ip(),
        ]);
    }

    public function render()
    {
        $summary = [
            'total' => WarrantyPolicy::count(),
            'product_scoped' => WarrantyPolicy::whereNotNull('product_id')->count(),
            'category_scoped' => WarrantyPolicy::whereNotNull('category_id')->count(),
            'avg_duration' => round(WarrantyPolicy::avg('duration_months') ?? 0, 1),
        ];

        return view('livewire.settings.warranty-policies.index', [
            'policies' => WarrantyPolicy::with(['product', 'category'])->latest('id')->paginate(10),
            'products' => Product::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'summary' => $summary,
        ]);
    }
}
