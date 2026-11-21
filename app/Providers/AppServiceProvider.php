<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn ($user, string $ability) => $user->hasRole('Dealer') ? true : null);

        // SMTP settings saved in Settings → Email & SMS override the .env mailer.
        \App\Services\MailSettings::apply();
    }
}
