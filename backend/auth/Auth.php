<?php

/**
 * Session-based authentication, role checks and CSRF protection.
 *
 * Every page and API endpoint that needs a signed-in user goes through this
 * class, so the security rules live in one place:
 *   - passwords are checked with password_verify() against password_hash() output
 *   - the session ID is regenerated on sign-in (prevents session fixation)
 *   - repeated failed sign-ins are rate limited (prevents brute force)
 *   - state-changing requests must carry the session's CSRF token
 *   - sign-out clears the session data, expires the cookie and destroys the session
 */
final class Auth
{
    public const ROLES = ['admin', 'registrar', 'faculty'];

    /** Sign the user out after 30 minutes without any request. */
    private const IDLE_TIMEOUT_SECONDS = 1800;

    /** Failed attempts allowed per username, and per IP address, in the lockout window. */
    private const MAX_ATTEMPTS_PER_USERNAME = 5;
    private const MAX_ATTEMPTS_PER_IP = 20;
    private const LOCKOUT_MINUTES = 15;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');      // reject session IDs the server never issued
        ini_set('session.use_only_cookies', '1');     // never accept a session ID from the URL
        session_set_cookie_params([
            'lifetime' => 0,                          // cookie ends when the browser closes
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),  // HTTPS-only once deployed over HTTPS
            'httponly' => true,                       // JavaScript cannot read the cookie (XSS)
            'samesite' => 'Lax',                      // not sent on cross-site POSTs (CSRF)
        ]);
        session_start();

        $lastActivity = $_SESSION['last_activity'] ?? null;
        if ($lastActivity !== null && time() - $lastActivity > self::IDLE_TIMEOUT_SECONDS) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['last_activity'] = time();
    }

    /**
     * True when this username, or this IP address, has failed too often
     * within the lockout window.
     */
    public static function tooManyAttempts(mysqli $conn, string $username): bool
    {
        return self::recentFailures($conn, 'username', self::limitUsername($username)) >= self::MAX_ATTEMPTS_PER_USERNAME
            || self::recentFailures($conn, 'ip_address', self::clientIp()) >= self::MAX_ATTEMPTS_PER_IP;
    }

    /**
     * Checks a username and password. Returns the user (without the password
     * hash) on success, or null on failure. Failures are recorded for rate
     * limiting; a success clears that username's failures.
     */
    public static function attempt(mysqli $conn, string $username, string $password): ?array
    {
        $stmt = $conn->prepare(
            'SELECT id, username, full_name, email, role, password_hash FROM users WHERE username = ?'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || !password_verify($password, $row['password_hash'])) {
            self::recordFailedAttempt($conn, $username);
            return null;
        }

        $clear = $conn->prepare('DELETE FROM login_attempts WHERE username = ?');
        $clear->bind_param('s', $row['username']);
        $clear->execute();

        // If PHP's default algorithm or cost has changed, upgrade the stored hash now,
        // while we still have the plain password.
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $update->bind_param('si', $newHash, $row['id']);
            $update->execute();
        }

        return [
            'id'       => (int) $row['id'],
            'username' => $row['username'],
            'name'     => $row['full_name'],
            'email'    => $row['email'],
            'role'     => $row['role'],
        ];
    }

    /** Starts an authenticated session for a user returned by attempt(). */
    public static function login(array $user): void
    {
        session_regenerate_id(true);                   // new ID: prevents session fixation
        $_SESSION['user'] = $user;
        $_SESSION['csrf'] = bin2hex(random_bytes(32)); // fresh CSRF token for this sign-in
        $_SESSION['last_activity'] = time();
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /** For pages: sends visitors who are not signed in to the login page. */
    public static function requireLogin(): array
    {
        $user = self::user();
        if ($user === null) {
            header('Location: login.php');
            exit;
        }
        return $user;
    }

    /** For pages: stops with 403 Forbidden unless the user has one of the roles. */
    public static function requireRole(array $roles): array
    {
        $user = self::requireLogin();
        if (!in_array($user['role'], $roles, true)) {
            http_response_code(403);
            exit('403 Forbidden — your account does not have access to this page.');
        }
        return $user;
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    /** Compares in constant time so the token cannot be guessed byte by byte. */
    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals(self::csrfToken(), $token);
    }

    /**
     * Sets the "419 Page Expired" status used for a missing or wrong CSRF
     * token. 419 is not a standard HTTP code, so the reason phrase is sent
     * too; without it Apache cannot build the status line and answers 500.
     */
    public static function sendCsrfFailedStatus(): void
    {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        header("$protocol 419 Page Expired", true, 419);
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    private static function recordFailedAttempt(mysqli $conn, string $username): void
    {
        $name = self::limitUsername($username);
        $ip = self::clientIp();
        $stmt = $conn->prepare('INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)');
        $stmt->bind_param('ss', $name, $ip);
        $stmt->execute();
    }

    /** $column is never user input: only 'username' or 'ip_address' are passed in. */
    private static function recentFailures(mysqli $conn, string $column, string $value): int
    {
        $column = $column === 'ip_address' ? 'ip_address' : 'username';
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM login_attempts
              WHERE {$column} = ?
                AND attempted_at > NOW() - INTERVAL " . self::LOCKOUT_MINUTES . ' MINUTE'
        );
        $stmt->bind_param('s', $value);
        $stmt->execute();
        return (int) $stmt->get_result()->fetch_row()[0];
    }

    /** login_attempts.username is VARCHAR(50). */
    private static function limitUsername(string $username): string
    {
        return mb_substr($username, 0, 50);
    }

    private static function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
