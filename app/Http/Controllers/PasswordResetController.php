<?php

namespace App\Http\Controllers;

use App\Models\Settings\AuditLog;
use App\Models\User;
use App\Notifications\PasswordResetRequested;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * "Forgot password" for the login page.
 *
 * With real outgoing mail configured, the user gets the standard Laravel
 * reset link by email. Without it (MAIL_MAILER=log), Dealers/Managers get an
 * in-app notification instead and set a new password in Settings → Users.
 * Either way the response never reveals whether the email exists.
 */
class PasswordResetController extends Controller
{
    public static function mailEnabled(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }

    public function showRequest()
    {
        return view('auth.forgot-password', ['mailEnabled' => self::mailEnabled()]);
    }

    public function sendRequest(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $user = User::where('email', $data['email'])->first();

        if ($user) {
            if (self::mailEnabled()) {
                try {
                    Password::sendResetLink(['email' => $user->email]);
                } catch (\Throwable $e) {
                    report($e);   // SMTP down: same answer to the user, error goes to the log
                }
            } else {
                $alreadyAsked = DB::table('notifications')
                    ->where('type', PasswordResetRequested::class)
                    ->where('created_at', '>=', now()->subMinutes(30))
                    ->where('data', 'like', '%'.addcslashes($user->email, '%_').'%')
                    ->exists();

                if (! $alreadyAsked) {
                    $admins = User::role(['Admin', 'Manager'])->whereKeyNot($user->id)->get();

                    if ($admins->isEmpty()) {
                        $admins = User::role('Admin')->get();
                    }

                    Notification::send($admins, new PasswordResetRequested($user, $request->ip()));
                }
            }
        }

        $message = self::mailEnabled()
            ? __('If that email belongs to an account, a password reset link has been sent to it.')
            : __('If that email belongs to an account, your administrator has been notified and will set a new password for you.');

        return back()->with('status', $message);
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($request) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'password.reset',
                    'auditable_type' => $user::class,
                    'auditable_id' => $user->id,
                    'ip_address' => $request->ip(),
                ]);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __('Your password has been reset. You can sign in now.'));
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
