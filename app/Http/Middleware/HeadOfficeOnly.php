<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Pages that only make sense for Agro Tech's head office, never for a dealer's login. */
class HeadOfficeOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_if($request->user()?->dealer_id !== null, 403);

        return $next($request);
    }
}
