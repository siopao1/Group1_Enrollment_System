<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SsoToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Single sign-on with the main app (one sign-in for both applications).
 *
 *   GET /sso?token=&to=   a one-time code from the main app:
 *                         'login'  → sign the same user in here, open <to>
 *                         'logout' → end this session too, back to the main sign-in page
 *   GET /go-main?to=      signed in here → one-time code → main app screen <to>
 *
 * Signing in itself happens on the main app's sign-in page.
 */
class SsoController extends Controller
{
    public function consume(Request $request): RedirectResponse
    {
        $code = SsoToken::consume((string) $request->query('token', ''));
        $mainLogin = config('app.main_app_url') . '/login.php';

        if ($code === null) {
            return redirect()->away($mainLogin);
        }

        if ($code['purpose'] === 'logout') {
            if (Auth::id() === $code['user_id']) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            return redirect()->away($mainLogin);
        }

        $user = User::find($code['user_id']);
        if (!$user) {
            return redirect()->away($mainLogin);
        }

        Auth::login($user);
        $request->session()->regenerate(); // new session ID: prevents session fixation

        // Only a path inside this module, never another website.
        $to = (string) $request->query('to', '/offerings');
        if (preg_match('#^/[A-Za-z0-9/_-]*$#', $to) !== 1 || str_starts_with($to, '//')) {
            $to = '/offerings';
        }

        return redirect($to);
    }

    public function goMain(Request $request): RedirectResponse
    {
        // A screen of the main app: "dashboard", "students", "reports", "settings".
        $to = (string) $request->query('to', 'dashboard');
        if (preg_match('/^[a-z-]+$/', $to) !== 1) {
            $to = 'dashboard';
        }

        $token = SsoToken::issue((int) Auth::id(), 'login');

        return redirect()->away(config('app.main_app_url') . "/sso.php?token={$token}&to={$to}");
    }
}
