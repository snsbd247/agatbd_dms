<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Switch the UI language (বাংলা / English). Saved on the user when logged
     * in, and in the session either way so the login page follows too.
     */
    public function __invoke(Request $request, string $locale)
    {
        abort_unless(array_key_exists($locale, SetLocale::SUPPORTED), 404);

        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->save();

        return back();
    }
}
