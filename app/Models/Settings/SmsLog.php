<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = ['to', 'message', 'event', 'status', 'response'];
}
