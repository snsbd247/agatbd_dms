<?php

namespace App\Http\Controllers;

use App\Models\Settings\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin(){return view('auth.login');}

    public function login(Request $request)
    {
        $c = $request->validate(['email' => 'required|email', 'password' => 'required']);

        if (! Auth::attempt($c)) {
            LoginHistory::create([
                'user_id' => User::where('email', $c['email'])->value('id'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'login_at' => now(),
                'status' => 'failed',
            ]);

            return back()->withErrors(['email' => 'Invalid credentials']);
        }

        $request->session()->regenerate();

        LoginHistory::create([
            'user_id' => Auth::id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_at' => now(),
            'status' => 'success',
        ]);

        return redirect()->intended('/dashboard');
    }

    public function logout(Request $request)
    {
        LoginHistory::where('user_id', Auth::id())
            ->whereNull('logout_at')
            ->latest('login_at')
            ->first()?->update(['logout_at' => now()]);

        Auth::logout();
        $request->session()->invalidate();

        return redirect('/');
    }
}
