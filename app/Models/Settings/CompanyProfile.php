<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $fillable = ['name', 'address', 'phone', 'email', 'logo_path', 'invoice_footer'];
}
