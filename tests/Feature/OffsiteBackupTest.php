<?php

namespace Tests\Feature;

use App\Livewire\More\OffsiteBackup;
use App\Models\Settings\AppSetting;
use App\Services\OffsiteBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Off-site copies of the nightly backup: by email or FTP. */
class OffsiteBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->actingAs($this->userWithRole('Admin'));
    }

    private function zip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dms').'.zip';
        file_put_contents($path, str_repeat('x', 2048));

        return $path;
    }

    public function test_settings_are_saved_and_the_ftp_password_is_encrypted(): void
    {
        Livewire::test(OffsiteBackup::class)
            ->set('method', 'ftp')->set('ftp_host', 'ftp.example.com')->set('ftp_user', 'u')->set('ftp_password', 'secret-pw')
            ->call('save')->assertHasNoErrors();

        $this->assertSame('ftp', OffsiteBackupService::method());
        $this->assertNotSame('secret-pw', AppSetting::get('offsite_ftp_password'));
        $this->assertSame('secret-pw', OffsiteBackupService::ftpPassword());

        // Saving again without a password keeps the old one.
        Livewire::test(OffsiteBackup::class)->set('ftp_path', '/x')->call('save');
        $this->assertSame('secret-pw', OffsiteBackupService::ftpPassword());

        Livewire::test(OffsiteBackup::class)->set('method', 'email')->set('email', 'not-an-email')->call('save')->assertHasErrors('email');
    }

    public function test_the_backup_is_emailed_when_mail_is_set_up(): void
    {
        Mail::fake();
        AppSetting::put(['offsite_method' => 'email', 'offsite_email' => 'owner@example.com', 'mail_enabled' => '1', 'mail_host' => 'smtp.example.com']);

        [$ok, $message] = app(OffsiteBackupService::class)->send($this->zip());

        $this->assertTrue($ok, $message);
        $this->assertStringContainsString('owner@example.com', $message);
        $this->assertTrue(AppSetting::flag('offsite_last_ok'));
    }

    public function test_a_failure_is_recorded_not_thrown(): void
    {
        AppSetting::put(['offsite_method' => 'email', 'offsite_email' => 'owner@example.com', 'mail_enabled' => '0']);
        [$ok, $message] = app(OffsiteBackupService::class)->send($this->zip());
        $this->assertFalse($ok);
        $this->assertStringContainsString('Email is not set up', $message);
        $this->assertFalse(AppSetting::flag('offsite_last_ok'));

        AppSetting::put(['offsite_method' => 'ftp', 'offsite_ftp_host' => '127.0.0.1', 'offsite_ftp_port' => '1', 'offsite_ftp_user' => 'u', 'offsite_ftp_ssl' => '0']);
        [$ok, $message] = app(OffsiteBackupService::class)->send($this->zip());
        $this->assertFalse($ok);
        $this->assertStringContainsString('127.0.0.1', $message);
    }

    public function test_the_backup_page_shows_the_off_site_section(): void
    {
        $this->get(route('more.backup-restore'))->assertOk()->assertSee('Off-site copy');
    }
}
