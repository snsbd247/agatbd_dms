<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CompanyProfile extends Model
{
    protected $fillable = ['name', 'address', 'phone', 'email', 'logo_path', 'invoice_footer'];

    /**
     * The logo as a data: URI, for PDFs (dompdf cannot load files outside its
     * chroot, and an inline image needs no web request).
     */
    public function logoDataUri(): ?string
    {
        if (! $this->logo_path || ! Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($this->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($this->logo_path));
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path && Storage::disk('public')->exists($this->logo_path)
            ? asset('storage/'.$this->logo_path)
            : null;
    }
}
