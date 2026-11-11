<?php

declare(strict_types=1);

/**
 * Works out who is calling the API, and whether their role is allowed.
 *
 * Two ways to authenticate:
 *   1. Bearer token — "Authorization: Bearer <token>". Used by Postman and any
 *      non-browser client. Tokens come from POST /api/auth/login, last 8 hours,
 *      and only their SHA-256 hash is stored in api_tokens.
 *   2. Session cookie — used by this app's own pages. Because the browser
 *      sends the cookie automatically, requests that change data must also
 *      carry the page's CSRF token in an X-CSRF-Token header.
 *      (Bearer requests need no CSRF token: a browser never adds that
 *      header on its own, so another site cannot forge it.)
 *
 * Errors: 401 not authenticated, 419 missing/invalid CSRF token, 403 role not allowed.
 */
final class ApiAuth
{
    private const TOKEN_LIFETIME_HOURS = 8;

    /** @param string[] $roles */
    public static function requireRole(array $roles): array
    {
        $user = self::authenticate();

        if (!in_array($user['role'], $roles, true)) {
            Response::error('You do not have permission to do this.', 403);
        }

        return $user;
    }

    public static function authenticate(): array
    {
        $header = Request::header('Authorization');

        if ($header !== null && $header !== '') {
            $token = self::parseBearer($header);
            if ($token === null) {
                self::unauthorized('Malformed Authorization header. Use: Bearer <token>.');
            }

            $user = self::userForToken($token);
            if ($user === null) {
                self::unauthorized('Invalid or expired token. Sign in again.');
            }

            return $user + ['authenticatedVia' => 'token'];
        }

        Auth::start();
        $user = Auth::user();
        if ($user === null) {
            self::unauthorized('Please sign in to continue.');
        }

        $changesData = !in_array(Request::method(), ['GET', 'HEAD', 'OPTIONS'], true);
        if ($changesData && !Auth::verifyCsrf(Request::header('X-CSRF-Token'))) {
            Response::error('Invalid or missing CSRF token. Refresh the page and try again.', 419);
        }

        // Release the session lock so the page's other API calls don't wait on this one.
        session_write_close();

        return $user + ['authenticatedVia' => 'session'];
    }

    /** Creates a token for a user. The plain token is returned once and never stored. */
    public static function issueToken(int $userId): array
    {
        $conn = db();
        $conn->query('DELETE FROM api_tokens WHERE expires_at < NOW()'); // housekeeping

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $hours = self::TOKEN_LIFETIME_HOURS;

        $stmt = $conn->prepare(
            "INSERT INTO api_tokens (user_id, token_hash, expires_at)
             VALUES (?, ?, NOW() + INTERVAL {$hours} HOUR)"
        );
        $stmt->bind_param('is', $userId, $hash);
        $stmt->execute();

        $lookup = $conn->prepare('SELECT expires_at FROM api_tokens WHERE id = ?');
        $tokenId = (int) $conn->insert_id;
        $lookup->bind_param('i', $tokenId);
        $lookup->execute();
        $expiresAt = (string) $lookup->get_result()->fetch_row()[0];

        return ['token' => $token, 'expiresAt' => $expiresAt];
    }

    /** Deletes the token sent in this request's Authorization header. */
    public static function revokeCurrentToken(): bool
    {
        $token = self::parseBearer((string) Request::header('Authorization'));
        if ($token === null) {
            return false;
        }

        $hash = hash('sha256', $token);
        $stmt = db()->prepare('DELETE FROM api_tokens WHERE token_hash = ?');
        $stmt->bind_param('s', $hash);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }

    private static function parseBearer(string $header): ?string
    {
        return preg_match('/^Bearer\s+([A-Fa-f0-9]{64})$/', $header, $match) === 1
            ? strtolower($match[1])
            : null;
    }

    private static function userForToken(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $stmt = db()->prepare(
            'SELECT u.id, u.username, u.full_name, u.email, u.role
               FROM api_tokens t
               JOIN users u ON u.id = t.user_id
              WHERE t.token_hash = ? AND t.expires_at > NOW()'
        );
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            return null;
        }

        return [
            'id'       => (int) $row['id'],
            'username' => $row['username'],
            'name'     => $row['full_name'],
            'email'    => $row['email'],
            'role'     => $row['role'],
        ];
    }

    private static function unauthorized(string $message): never
    {
        header('WWW-Authenticate: Bearer');
        Response::error($message, 401);
    }
}
