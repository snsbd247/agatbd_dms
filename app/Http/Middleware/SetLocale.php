<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pick the UI language for this request: the logged-in user's choice, else
 * the choice saved in the session (login page), else APP_LOCALE (bn).
 * Runs on every web request, including Livewire updates.
 */
class SetLocale
{
    public const SUPPORTED = ['bn' => 'বাংলা', 'en' => 'English'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?: $request->session()->get('locale') ?: config('app.locale');

        if (! array_key_exists($locale, self::SUPPORTED)) {
            $locale = config('app.fallback_locale', 'en');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
