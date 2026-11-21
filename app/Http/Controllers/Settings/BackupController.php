<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\BackupService;

class BackupController extends Controller
{
    /**
     * Take a fresh backup and download it.
     */
    public function download(BackupService $backups)
    {
        $zip = $backups->create('manual');

        return response()->download($zip, basename($zip), ['Content-Type' => 'application/zip']);
    }

    /**
     * Download one of the stored (automatic) backups.
     */
    public function downloadStored(string $name, BackupService $backups)
    {
        $path = $backups->path($name);
        abort_unless($path, 404);

        return response()->download($path, $name, ['Content-Type' => 'application/zip']);
    }
}
