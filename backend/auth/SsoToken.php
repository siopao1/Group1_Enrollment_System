<?php

declare(strict_types=1);

/**
 * One-time codes for single sign-on between the main app and the Laravel
 * module (table sso_tokens). The Laravel module has the same logic in
 * app/Support/SsoToken.php; both work on the shared database.
 *
 *   purpose 'login'  — sign the user in on the other app
 *   purpose 'logout' — end the user's session on the other app
 *
 * A code is 64 random hex characters, valid for LIFETIME_SECONDS, stored only
 * as its SHA-256 hash, and deleted when it is used, so it works once.
 */
final class SsoToken
{
    public const LIFETIME_SECONDS = 60;

    public static function issue(mysqli $conn, int $userId, string $purpose): string
    {
        $conn->query('DELETE FROM sso_tokens WHERE expires_at < NOW()'); // housekeeping

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $lifetime = self::LIFETIME_SECONDS;
        $stmt = $conn->prepare(
            "INSERT INTO sso_tokens (user_id, purpose, token_hash, expires_at)
             VALUES (?, ?, ?, NOW() + INTERVAL {$lifetime} SECOND)"
        );
        $stmt->bind_param('iss', $userId, $purpose, $hash);
        $stmt->execute();

        return $token;
    }

    /**
     * Uses up a code. Returns ['user_id' => int, 'purpose' => string], or null
     * when the code is unknown, expired or already used.
     */
    public static function consume(mysqli $conn, string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }
        $hash = hash('sha256', $token);

        $stmt = $conn->prepare('SELECT id, user_id, purpose FROM sso_tokens WHERE token_hash = ? AND expires_at > NOW()');
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            return null;
        }

        // Delete before use; if another request already deleted it, refuse.
        $delete = $conn->prepare('DELETE FROM sso_tokens WHERE id = ?');
        $delete->bind_param('i', $row['id']);
        $delete->execute();
        if ($delete->affected_rows !== 1) {
            return null;
        }

        return ['user_id' => (int) $row['user_id'], 'purpose' => (string) $row['purpose']];
    }
}
