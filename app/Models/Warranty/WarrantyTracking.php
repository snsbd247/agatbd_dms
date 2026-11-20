<?php

namespace App\Models\Warranty;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WarrantyTracking extends Model
{
    protected $table = 'warranty_tracking';

    protected $fillable = ['warranty_claim_id', 'status', 'note', 'changed_by', 'changed_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function warrantyClaim()
    {
        return $this->belongsTo(WarrantyClaim::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
