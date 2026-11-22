<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pick the UI language for this request: the logged-in user's own choice
 * (kept on the user, so it comes back at every login), else the choice
 * made on the login page (session), else English.
 * Runs on every web request, including Livewire updates.
 */
class SetLocale
{
    public const SUPPORTED = ['bn' => 'বাংলা', 'en' => 'English'];

    /** Until a user picks a language. */
    public const DEFAULT = 'en';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?: $request->session()->get('locale') ?: self::DEFAULT;

        if (! array_key_exists($locale, self::SUPPORTED)) {
            $locale = config('app.fallback_locale', 'en');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
