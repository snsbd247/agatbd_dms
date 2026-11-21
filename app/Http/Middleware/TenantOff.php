<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;

/**
 * For portal pages: switch the dealer filter off for this request. Every
 * portal page limits itself to the logged-in customer's own account (a
 * retailer, or a dealer's account with the head office), which the head
 * office owns and the dealer filter would otherwise hide.
 */
class TenantOff
{
    public function handle(Request $request, Closure $next)
    {
        return Tenant::bypass(fn () => $next($request));
    }
}
