<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::group([], base_path('routes/device.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [\App\Http\Middleware\SetLocale::class]);
        $middleware->alias(['tenant.off' => \App\Http\Middleware\TenantOff::class, 'head.office' => \App\Http\Middleware\HeadOfficeOnly::class, 'module' => \App\Http\Middleware\EnsureModule::class]);
        // Route-model binding must already see the portal's records with the dealer filter off.
        $middleware->prependToPriorityList(before: \Illuminate\Routing\Middleware\SubstituteBindings::class, prepend: \App\Http\Middleware\TenantOff::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
