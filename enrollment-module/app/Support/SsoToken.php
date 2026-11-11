<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * One-time codes for single sign-on with the main app (table sso_tokens,
 * created by the main app's schema.sql). Same rules as the main app's
 * backend/auth/SsoToken.php: 64 random hex characters, valid 60 seconds,
 * stored only as a SHA-256 hash, deleted when used.
 */
class SsoToken
{
    public const LIFETIME_SECONDS = 60;

    public static function issue(int $userId, string $purpose): string
    {
        DB::table('sso_tokens')->where('expires_at', '<', DB::raw('NOW()'))->delete();

        $token = bin2hex(random_bytes(32));
        DB::table('sso_tokens')->insert([
            'user_id'    => $userId,
            'purpose'    => $purpose,
            'token_hash' => hash('sha256', $token),
            'expires_at' => DB::raw('NOW() + INTERVAL ' . self::LIFETIME_SECONDS . ' SECOND'),
        ]);

        return $token;
    }

    /** @return array{user_id: int, purpose: string}|null */
    public static function consume(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }

        $row = DB::table('sso_tokens')
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', DB::raw('NOW()'))
            ->first();
        if (!$row) {
            return null;
        }

        // Delete before use; if another request already deleted it, refuse.
        if (DB::table('sso_tokens')->where('id', $row->id)->delete() !== 1) {
            return null;
        }

        return ['user_id' => (int) $row->user_id, 'purpose' => (string) $row->purpose];
    }
}
