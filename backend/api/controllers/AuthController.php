<?php

declare(strict_types=1);

/**
 * /api/auth — token sign-in for API clients such as Postman.
 * (The app's own pages sign in through login.php and a session cookie.)
 *
 *   POST /api/auth/login   200 + token | 401 | 422 | 429
 *   POST /api/auth/logout  204 (token revoked, or session ended)
 *   GET  /api/auth/me      200 current user
 */
final class AuthController
{
    public function login(): never
    {
        $input = Request::json();
        $username = is_string($input['username'] ?? null) ? trim($input['username']) : '';
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';

        $errors = [];
        if ($username === '') {
            $errors['username'][] = 'Username is required.';
        }
        if ($password === '') {
            $errors['password'][] = 'Password is required.';
        }
        if ($errors !== []) {
            Response::error('Validation failed.', 422, $errors);
        }

        // Same rate limit as the login page: 5 failures per username, 20 per IP, per 15 minutes.
        if (Auth::tooManyAttempts(db(), $username)) {
            header('Retry-After: 900');
            Response::error('Too many failed sign-in attempts. Try again in 15 minutes.', 429);
        }

        $user = Auth::attempt(db(), $username, $password);
        if ($user === null) {
            Response::error('Invalid username or password.', 401);
        }

        $token = ApiAuth::issueToken($user['id']);

        Response::success('Signed in.', [
            'token'     => $token['token'],
            'tokenType' => 'Bearer',
            'expiresAt' => $token['expiresAt'],
            'user'      => $user,
        ]);
    }

    public function logout(array $user): never
    {
        if ($user['authenticatedVia'] === 'token') {
            ApiAuth::revokeCurrentToken();
        } else {
            Auth::start(); // reopen the session that ApiAuth closed for reading
            Auth::logout();
        }

        Response::noContent();
    }

    public function me(array $user): never
    {
        Response::success('Current user.', $user);
    }
}
