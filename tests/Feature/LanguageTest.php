<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** English until a user picks a language; their pick comes back at every login. */
class LanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_by_default_and_the_choice_is_kept_across_logins(): void
    {
        $this->seedRoles();
        config(['app.locale' => 'bn']);   // even if the server's .env says Bangla
        $user = $this->userWithRole('Admin', ['password' => Hash::make('secret-pass'), 'locale' => null]);

        $this->get(route('login'))->assertOk();
        $this->assertSame('en', app()->getLocale());

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-pass'])->assertRedirect();
        $this->get(route('help'))->assertOk();
        $this->assertSame('en', app()->getLocale(), 'no choice yet: English');

        $this->post(route('locale.switch', 'bn'))->assertRedirect();
        $this->assertSame('bn', $user->fresh()->locale);

        $this->post(route('logout'));
        $this->flushSession();
        $this->get(route('login'));
        $this->assertSame('en', app()->getLocale(), 'login page of a new visit is English');

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-pass']);
        $this->get(route('help'))->assertOk();
        $this->assertSame('bn', app()->getLocale(), 'Bangla again after logging in');
    }

    public function test_a_language_picked_on_the_login_page_is_saved_on_login(): void
    {
        $this->seedRoles();
        $user = $this->userWithRole('Manager', ['password' => Hash::make('secret-pass'), 'locale' => null]);

        $this->post(route('locale.switch', 'bn'));
        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-pass']);

        $this->assertSame('bn', $user->fresh()->locale);
    }
}
