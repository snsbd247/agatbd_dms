<?php

namespace App\Livewire\More;

use App\Models\Settings\AuditLog;
use App\Services\BackupService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Backup & Restore')]
class BackupRestore extends Component
{
    use WithFileUploads;

    public $upload;

    /** Name of a stored backup chosen for restore, or 'upload'. */
    public ?string $restoreSource = null;

    public string $confirmPassword = '';
    public string $confirmText = '';

    public ?array $result = null;

    public function chooseStored(string $name): void
    {
        $this->authorize('settings.manage');
        $this->restoreSource = $name;
        $this->reset(['confirmPassword', 'confirmText', 'result']);
        $this->resetValidation();
    }

    public function chooseUpload(): void
    {
        $this->authorize('settings.manage');

        $this->validate(['upload' => 'required|file|max:102400'], [], ['upload' => 'backup file']);

        $this->restoreSource = 'upload';
        $this->reset(['confirmPassword', 'confirmText', 'result']);
    }

    public function cancel(): void
    {
        $this->reset(['restoreSource', 'confirmPassword', 'confirmText']);
        $this->resetValidation();
    }

    public function restore(BackupService $backups): void
    {
        $this->authorize('settings.manage');

        $this->validate([
            'confirmPassword' => 'required',
            'confirmText' => 'required|in:RESTORE',
        ], [
            'confirmText.in' => 'Type RESTORE in capital letters to confirm.',
        ]);

        if (! Hash::check($this->confirmPassword, Auth::user()->password)) {
            $this->addError('confirmPassword', 'Your password is not correct.');

            return;
        }

        $file = $this->restoreSource === 'upload'
            ? $this->upload?->getRealPath()
            : $backups->path((string) $this->restoreSource);

        if (! $file) {
            $this->addError('restore', 'The selected backup file was not found.');

            return;
        }

        $label = $this->restoreSource === 'upload' ? $this->upload->getClientOriginalName() : $this->restoreSource;
        $userId = Auth::id();
        $ip = request()->ip();

        try {
            $result = $backups->restore($file);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('restore', 'Restore failed, nothing was changed: '.$e->getMessage());

            return;
        }

        AuditLog::create([
            'user_id' => \App\Models\User::whereKey($userId)->exists() ? $userId : null,
            'action' => 'backup.restored',
            'new_values' => ['file' => $label] + $result,
            'ip_address' => $ip,
        ]);

        $this->result = $result + ['file' => $label];
        $this->reset(['restoreSource', 'confirmPassword', 'confirmText', 'upload']);
    }

    public function render(BackupService $backups)
    {
        return view('livewire.more.backup-restore', [
            'stored' => $backups->list(30),
        ]);
    }
}
