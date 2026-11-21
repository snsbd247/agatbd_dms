<?php

namespace Tests\Feature;

use App\Models\Settings\AppSetting;
use App\Models\Settings\AuditLog;
use App\Models\Settings\SmsLog;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuditAndMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_changes_are_audited_with_old_and_new_values(): void
    {
        $this->seedRoles();
        $this->actingAs($this->userWithRole('Admin'));

        $p = $this->product(['sale_price' => 100]);
        $p->update(['sale_price' => 120]);

        $log = AuditLog::where('action', 'product.updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals(100, $log->old_values['sale_price']);
        $this->assertEquals(120, $log->new_values['sale_price']);
        $this->assertArrayNotHasKey('updated_at', $log->new_values);
    }

    public function test_nothing_is_audited_for_console_runs_without_a_user(): void
    {
        $this->product();
        $this->assertSame(0, AuditLog::count());
    }

    public function test_secrets_are_stored_encrypted(): void
    {
        AppSetting::put(['sms_api_key' => 'TOPSECRET', 'mail_password' => 'pw123']);

        $this->assertStringNotContainsString('TOPSECRET', (string) DB::table('app_settings')->where('key', 'sms_api_key')->value('value'));
        $this->assertSame('TOPSECRET', AppSetting::get('sms_api_key'));
    }

    public function test_sms_goes_through_gateway_with_normalised_number_and_hidden_key(): void
    {
        Http::fake(['gateway.test/*' => Http::response('OK TOPSECRET accepted', 200)]);
        AppSetting::put([
            'sms_enabled' => true,
            'sms_url' => 'https://gateway.test/send?key={api_key}&to={to}&msg={message}',
            'sms_api_key' => 'TOPSECRET',
            'sms_number_format' => '880',
        ]);

        $this->assertTrue(app(SmsService::class)->send('017-1122 3344', 'Hello', 'delivered'));

        Http::assertSent(fn ($r) => str_contains($r->url(), 'to=8801711223344') && str_contains($r->url(), 'msg=Hello'));
        $log = SmsLog::first();
        $this->assertSame('sent', $log->status);
        $this->assertStringNotContainsString('TOPSECRET', (string) $log->response);
    }

    public function test_sms_failure_is_logged_not_thrown_and_disabled_events_are_skipped(): void
    {
        Http::fake(['*' => Http::response('down', 500)]);
        AppSetting::put(['sms_enabled' => true, 'sms_url' => 'https://gateway.test/?to={to}', 'sms_event_payment_received' => false]);

        $this->assertFalse(app(SmsService::class)->send('01711223344', 'x', 'delivered'));
        $this->assertSame('failed', SmsLog::first()->status);

        $this->assertFalse(app(SmsService::class)->send('01711223344', 'x', 'payment_received'));
        $this->assertSame(1, SmsLog::count());
    }
}
