<?php

namespace Tests\Feature;

use App\Notifications\PasswordResetRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_without_mail_the_admins_are_notified_and_the_answer_is_the_same_for_unknown_emails(): void
    {
        Notification::fake();
        $dealer = $this->userWithRole('Dealer');
        $rep = $this->userWithRole('Sales Representative', ['email' => 'rep@example.com']);

        $known = $this->post(route('password.email'), ['email' => 'rep@example.com'])->getSession()->get('status');
        $unknown = $this->post(route('password.email'), ['email' => 'nobody@example.com'])->getSession()->get('status');

        $this->assertSame($known, $unknown);
        Notification::assertSentTo($dealer, PasswordResetRequested::class);
        Notification::assertNotSentTo($rep, PasswordResetRequested::class);
    }

    public function test_reset_link_sets_a_new_password_once(): void
    {
        $user = $this->userWithRole('Manager', ['email' => 'm@example.com']);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'm@example.com',
            'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));

        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'm@example.com',
            'password' => 'Another123', 'password_confirmation' => 'Another123',
        ])->assertSessionHasErrors('email');
    }
}
