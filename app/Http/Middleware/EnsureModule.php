<?php

namespace App\Http\Middleware;

use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;

/** route middleware `module:<key>` — the page belongs to a module that may be switched off. */
class EnsureModule
{
    public function handle(Request $request, Closure $next, string ...$modules)
    {
        foreach ($modules as $module) {
            abort_unless(Modules::enabled($module), 403, __('This module is switched off. An admin can switch it on in Settings → Business Type & Modules.'));
        }

        return $next($request);
    }
}
