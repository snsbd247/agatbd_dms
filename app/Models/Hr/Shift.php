<?php

namespace App\Models\Hr;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use Auditable;

    protected $fillable = ['name', 'start_time', 'end_time', 'grace_minutes', 'weekly_off'];
}
