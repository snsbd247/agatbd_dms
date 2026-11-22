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
        // A switched-off module is refused to everyone, Admin included (see App\Support\Modules).
        Gate::before(fn ($user, string $ability) => \App\Support\Modules::denies($user, $ability) ? false : null);
        Gate::before(fn ($user, string $ability) => $user->hasRole('Admin') ? true : null);
        // Then the role permissions (spatie), registered here so they come after the module check.
        app(\Spatie\Permission\PermissionRegistrar::class)->registerPermissions(app(\Illuminate\Contracts\Auth\Access\Gate::class));

        // SMTP settings saved in Settings → Email & SMS override the .env mailer.
        \App\Services\MailSettings::apply();

        // Portal pages switch the dealer filter off; keep it off for their Livewire updates too.
        \Livewire\Livewire::addPersistentMiddleware([\App\Http\Middleware\TenantOff::class]);
    }
}
