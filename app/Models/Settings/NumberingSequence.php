<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class NumberingSequence extends Model
{
    protected $fillable = ['key', 'year', 'last_number'];
}
