<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sign-in for the Laravel module, using the same accounts as the main app
 * (the `users` table). Laravel's Hash checks the bcrypt hash that PHP's
 * password_hash() stored.
 */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;      // per username + IP address
    private const DECAY_SECONDS = 60;

    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($credentials['username']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'username' => "Too many sign-in attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (!Auth::attempt($credentials)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            // One message for both cases, so usernames cannot be guessed.
            throw ValidationException::withMessages([
                'username' => 'Invalid username or password.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate(); // new session ID: prevents session fixation

        return redirect()->intended(route('offerings.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken(); // new CSRF token

        return redirect()->route('login');
    }
}
