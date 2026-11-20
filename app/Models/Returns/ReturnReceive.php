<?php

namespace App\Models\Returns;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

class ReturnReceive extends Model
{
    protected $fillable = ['number', 'return_request_id', 'received_by', 'warehouse_id', 'receive_date'];

    protected function casts(): array
    {
        return ['receive_date' => 'date'];
    }

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(ReturnReceiveItem::class);
    }
}
